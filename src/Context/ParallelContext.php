<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\ByteStream\StreamChannel;
use Amp\Cancellation;
use Amp\Parallel\Context\Internal\AbstractContext;
use Amp\Parallel\Context\Internal\ParallelHub;
use Amp\Parallel\Ipc\IpcHub;
use Amp\TimeoutCancellation;
use parallel\Runtime;
use parallel\Runtime\Error\Closed;
use Revolt\EventLoop;
use function Amp\Parallel\Context\Internal\runTasks;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-implements Context<TResult, TReceive, TSend>
 */
final class ParallelContext extends AbstractContext
{
    private const EXIT_CHECK_FREQUENCY = 0.25;
    private const DEFAULT_START_TIMEOUT = 5;

    private static ?\WeakMap $hubs = null;

    /**
     * Checks if threading is enabled.
     *
     * @return bool True if threading is enabled, otherwise false.
     */
    public static function isSupported(): bool
    {
        return \extension_loaded('parallel');
    }

    public static function start(
        IpcHub $ipcHub,
        string|array $script,
        ?Cancellation $cancellation = null,
        int $childConnectTimeout = self::DEFAULT_START_TIMEOUT
    ): self {
        /** @psalm-suppress RedundantFunctionCall */
        $script = \is_array($script) ? \array_values($script) : [$script];
        if (!$script) {
            throw new \ValueError('Empty script array provided to process context');
        }

        self::$hubs ??= new \WeakMap();
        $hub = (self::$hubs[EventLoop::getDriver()] ??= new Internal\ParallelHub($ipcHub));

        $key = $ipcHub->generateKey();

        if (self::$autoloadPath === null) {
            $paths = [
                \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . "vendor" . \DIRECTORY_SEPARATOR . "autoload.php",
                \dirname(__DIR__, 4) . \DIRECTORY_SEPARATOR . "autoload.php",
            ];

            foreach ($paths as $path) {
                if (\file_exists($path)) {
                    self::$autoloadPath = $path;
                    break;
                }
            }

            if (self::$autoloadPath === null) {
                throw new \Error("Could not locate autoload.php");
            }
        }

        $id = self::$nextId++;

        $runtime = new Runtime(self::$autoloadPath);
        $future = $runtime->run(function (
            int $id,
            string $uri,
            string $key,
            float $connectTimeout,
            array $argv,
        ) {
            // @codeCoverageIgnoreStart
            // Only executed in thread.
            \define("AMP_CONTEXT", "parallel");
            \define("AMP_CONTEXT_ID", $id);

            EventLoop::unreference(EventLoop::repeat(self::EXIT_CHECK_FREQUENCY, function (): void {
                // Timer to give the chance for the PHP VM to be interrupted by Runtime::kill(), since system calls
                // such as select() will not be interrupted.
            }));

            runTasks($uri, $key, new TimeoutCancellation($connectTimeout), $argv);

            return 0;
            // @codeCoverageIgnoreEnd
        }, [$id, $ipcHub->getUri(), $key, $childConnectTimeout, $script]);

        try {
            $socket = $ipcHub->accept($key, $cancellation);
            $channel = new StreamChannel($socket, $socket);
            $hub->add($id, $channel, $future);
        } catch (\Throwable $exception) {
            $runtime->kill();

            $cancellation?->throwIfRequested();

            throw new ContextException("Starting the runtime failed", 0, $exception);
        }

        return new self($id, $runtime, $hub, $channel);
    }

    /** @var int Next thread ID. */
    private static int $nextId = 1;

    private static ?string $autoloadPath = null;

    private readonly int $oid;

    private function __construct(
        private readonly int $id,
        private readonly Runtime $runtime,
        private readonly ParallelHub $hub,
        private readonly StreamChannel $channel,
    ) {
        parent::__construct($this->channel);

        $this->oid = \getmypid();
    }

    /**
     * Kills the thread if it is still running.
     */
    public function __destruct()
    {
        if (\getmypid() === $this->oid) {
            $this->close();
        }
    }

    public function close(): void
    {
        try {
            $this->runtime->kill();
        } catch (Closed) {
            // ignore
        }

        $this->hub->remove($this->id);

        parent::close();
    }

    public function join(?Cancellation $cancellation = null): mixed
    {
        $data = $this->receiveExitResult($cancellation);
        $this->runtime->close();

        return $data->getResult();
    }
}
