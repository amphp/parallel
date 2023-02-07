<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\StreamChannel;
use Amp\ByteStream\WritableResourceStream;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Parallel\Ipc;
use Amp\Parallel\Ipc\IpcHub;
use Amp\Parallel\Ipc\SynchronizationError;
use Amp\Process\Process;
use Amp\Process\ProcessException;
use Amp\Sync\ChannelException;
use Amp\TimeoutCancellation;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-implements Context<TResult, TReceive, TSend>
 */
final class ProcessContext implements Context
{
    use ForbidCloning;
    use ForbidSerialization;

    private const SCRIPT_PATH = __DIR__ . "/Internal/process-runner.php";
    private const DEFAULT_START_TIMEOUT = 5;

    private const DEFAULT_OPTIONS = [
        "html_errors" => "0",
        "display_errors" => "0",
        "log_errors" => "1",
    ];

    private const XDEBUG_OPTIONS = [
        "xdebug.mode" => "debug",
        "xdebug.start_with_request" => "default",
        "xdebug.client_port" => "9003",
        "xdebug.client_host" => "localhost",
    ];

    /** @var string|null External version of SCRIPT_PATH if inside a PHAR. */
    private static ?string $pharScriptPath = null;

    /** @var string|null PHAR path with a '.phar' extension. */
    private static ?string $pharCopy = null;

    /** @var non-empty-list<string>|null Cached path to located PHP binary. */
    private static ?array $binary = null;

    /** @var list<string>|null */
    private static ?array $options = null;

    /** @var list<int> */
    private static ?array $ignoredSignals = null;

    /**
     * @param string|non-empty-list<string> $script Path to PHP script or array with first element as path and
     *     following elements options to the PHP script (e.g.: ['bin/worker.php', 'Option1Value', 'Option2Value']).
     * @param string|null $workingDirectory Working directory.
     * @param array<string, string> $environment Array of environment variables, or use an empty array to inherit from
     *     the parent.
     * @param string|non-empty-list<string>|null $binary Path to PHP binary or array of binary path and options.
     *      Null will attempt to automatically locate the binary.
     * @param positive-int $childConnectTimeout Number of seconds the child will attempt to connect to the parent
     *      before failing.
     * @param IpcHub|null $ipcHub Optional IpcHub instance. Global IpcHub instance used if null.
     *
     * @return ProcessContext<TResult, TReceive, TSend>
     *
     * @throws ContextException If starting the process fails.
     */
    public static function start(
        string|array $script,
        ?string $workingDirectory = null,
        array $environment = [],
        ?Cancellation $cancellation = null,
        string|array|null $binary = null,
        int $childConnectTimeout = self::DEFAULT_START_TIMEOUT,
        ?IpcHub $ipcHub = null
    ): self {
        $ipcHub ??= Ipc\ipcHub();

        /** @psalm-suppress RedundantFunctionCall */
        $script = \is_array($script) ? \array_values($script) : [$script];
        if (!$script) {
            throw new \ValueError('Empty script array provided to process context');
        }

        if ($binary === null) {
            $binary = self::$binary ??= self::locateBinary();
        } else {
            /** @psalm-suppress RedundantFunctionCall */
            $binary = \is_array($binary) ? \array_values($binary) : [$binary];
            if (!$binary) {
                throw new \ValueError('Empty binary array provided to process context');
            }

            if (!\is_executable($binary[0])) {
                throw new \ValueError(
                    \sprintf("The PHP binary path '%s' was not found or is not executable", $binary[0])
                );
            }
        }

        // Write process runner to external file if inside a PHAR,
        // because PHP can't open files inside a PHAR directly except for the stub.
        if (\str_starts_with(self::SCRIPT_PATH, "phar://")) {
            if (self::$pharScriptPath) {
                $scriptPath = self::$pharScriptPath;
            } else {
                $path = \dirname(self::SCRIPT_PATH);

                if (!\str_ends_with(\Phar::running(false), ".phar")) {
                    self::$pharCopy = \sys_get_temp_dir() . "/phar-" . \bin2hex(\random_bytes(10)) . ".phar";
                    \copy(\Phar::running(false), self::$pharCopy);

                    \register_shutdown_function(static function (): void {
                        if (self::$pharCopy) {
                            @\unlink(self::$pharCopy);
                        }
                    });

                    $path = "phar://" . self::$pharCopy . "/" . \substr($path, \strlen(\Phar::running(true)));
                }

                $contents = \file_get_contents(self::SCRIPT_PATH);
                $contents = \str_replace("__DIR__", \var_export($path, true), $contents);
                $suffix = \bin2hex(\random_bytes(10));
                self::$pharScriptPath = $scriptPath = \sys_get_temp_dir() . "/amp-process-runner-" . $suffix . ".php";
                \file_put_contents($scriptPath, $contents);

                \register_shutdown_function(static function (): void {
                    if (self::$pharScriptPath) {
                        @\unlink(self::$pharScriptPath);
                    }
                });
            }

            // Monkey-patch the script path in the same way, only supported if the command is given as array.
            if (isset(self::$pharCopy)) {
                $script[0] = "phar://" . self::$pharCopy . \substr($script[0], \strlen(\Phar::running(true)));
            }
        } else {
            $scriptPath = self::SCRIPT_PATH;
        }

        $key = $ipcHub->generateKey();

        /** @var list<string> $command */
        $command = [
            ...$binary,
            ...(self::$options ??= self::buildOptions()),
            $scriptPath,
            $ipcHub->getUri(),
            (string) \strlen($key),
            (string) $childConnectTimeout,
            ...$script,
        ];

        try {
            $process = Process::start($command, $workingDirectory, $environment);
        } catch (\Throwable $exception) {
            throw new ContextException("Starting the process failed", 0, $exception);
        }

        try {
            $process->getStdin()->write($key);

            $socket = $ipcHub->accept($key, $cancellation);
            $channel = new StreamChannel($socket, $socket);
        } catch (\Throwable $exception) {
            if ($process->isRunning()) {
                $process->kill();
            }

            $cancellation?->throwIfRequested();

            throw new ContextException("Starting the process failed", 0, $exception);
        }

        return new self($process, $channel);
    }

    /**
     * @return non-empty-list<string>
     */
    private static function locateBinary(): array
    {
        if (\PHP_SAPI === "cli") {
            return [\PHP_BINARY];
        } elseif (\PHP_SAPI === "phpdbg") {
            return [\PHP_BINARY, '-qrr'];
        }

        $executable = \PHP_OS_FAMILY === 'Windows' ? "php.exe" : "php";

        $paths = \array_filter(\explode(
            \PATH_SEPARATOR,
            \getenv('PATH') ?: '/usr/bin' . \PATH_SEPARATOR . '/usr/local/bin',
        ));
        $paths[] = \PHP_BINDIR;
        $paths = \array_unique($paths);

        foreach ($paths as $path) {
            $path .= \DIRECTORY_SEPARATOR . $executable;
            if (\is_executable($path)) {
                return [$path];
            }
        }

        throw new \Error("Could not locate PHP executable binary");
    }

    /**
     * @return list<string>
     */
    private static function buildOptions(): array
    {
        $options = self::DEFAULT_OPTIONS;

        // This copies any ini values set via the command line (e.g., a debug run in PhpStorm)
        // to the child process, instead of relying only on those set in an ini file.
        if (\ini_get("xdebug.mode") !== false) {
            foreach (self::XDEBUG_OPTIONS as $option => $defaultValue) {
                $iniValue = \ini_get($option);
                $options[$option] = $iniValue === false ? $defaultValue : $iniValue;
            }
        }

        $result = [];

        foreach ($options as $option => $value) {
            $result[] = \sprintf("-d%s=%s", $option, $value);
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    public static function getIgnoredSignals(): array
    {
        return self::$ignoredSignals ??= [
            \defined('SIGHUP') ? \SIGHUP : 1,
            \defined('SIGINT') ? \SIGINT : 2,
            \defined('SIGQUIT') ? \SIGQUIT : 3,
            \defined('SIGTERM') ? \SIGTERM : 15,
            \defined('SIGALRM') ? \SIGALRM : 14,
            \defined('SIGUSR1') ? \SIGUSR1 : 10,
            \defined('SIGUSR2') ? \SIGUSR2 : 12,
        ];
    }

    /**
     * @param StreamChannel<TReceive, TSend> $channel
     */
    private function __construct(
        private readonly Process $process,
        private readonly StreamChannel $channel,
    ) {
    }

    /**
     * Always throws to prevent cloning.
     */
    public function __clone()
    {
        throw new \Error(self::class . ' objects cannot be cloned');
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        try {
            $data = $this->channel->receive($cancellation);
        } catch (ChannelException $exception) {
            try {
                $data = $this->join(new TimeoutCancellation(0.1));
            } catch (ContextException|ChannelException|CancelledException) {
                if (!$this->isClosed()) {
                    $this->close();
                }
                throw new ContextException(
                    "The process stopped responding, potentially due to a fatal error or calling exit",
                    0,
                    $exception,
                );
            }

            throw new SynchronizationError(\sprintf(
                'Process unexpectedly exited when waiting to receive data with result: %s',
                flattenArgument($data),
            ), 0, $exception);
        }

        if (!$data instanceof Internal\ContextMessage) {
            if ($data instanceof Internal\ExitResult) {
                $data = $data->getResult();

                throw new SynchronizationError(\sprintf(
                    'Process unexpectedly exited when waiting to receive data with result: %s',
                    flattenArgument($data),
                ));
            }

            throw new SynchronizationError(\sprintf(
                'Unexpected data type from context: %s',
                flattenArgument($data),
            ));
        }

        return $data->getMessage();
    }

    public function send(mixed $data): void
    {
        try {
            $this->channel->send($data);
        } catch (ChannelException $exception) {
            try {
                $data = $this->join(new TimeoutCancellation(0.1));
            } catch (ContextException|ChannelException|CancelledException) {
                if (!$this->isClosed()) {
                    $this->close();
                }
                throw new ContextException(
                    "The process stopped responding, potentially due to a fatal error or calling exit",
                    0,
                    $exception,
                );
            }

            throw new SynchronizationError(\sprintf(
                'Process unexpectedly exited when sending data with result: %s',
                flattenArgument($data),
            ), 0, $exception);
        }
    }

    /**
     * @return TResult
     * @throws ContextException
     */
    public function join(?Cancellation $cancellation = null): mixed
    {
        try {
            $data = $this->channel->receive($cancellation);
        } catch (CancelledException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if (!$this->isClosed()) {
                $this->close();
            }
            throw new ContextException("Failed to receive result from process", 0, $exception);
        }

        if (!$data instanceof Internal\ExitResult) {
            if (!$this->isClosed()) {
                $this->close();
            }

            if ($data instanceof Internal\ContextMessage) {
                throw new SynchronizationError(\sprintf(
                    "The process sent data instead of exiting: %s",
                    flattenArgument($data),
                ));
            }

            throw new SynchronizationError("Did not receive an exit result from process");
        }

        $this->channel->close();

        $code = $this->process->join();
        if ($code !== 0) {
            throw new ContextException(\sprintf("Process exited with code %d", $code));
        }

        return $data->getResult();
    }

    /**
     * Send a signal to the process.
     *
     * @throws StatusError|ProcessException
     * @see Process::signal()
     */
    public function signal(int $signo): void
    {
        $this->process->signal($signo);
    }

    /**
     * Returns the PID of the process.
     *
     * @throws StatusError
     * @see Process::getPid()
     */
    public function getPid(): int
    {
        return $this->process->getPid();
    }

    /**
     * Returns the STDIN stream of the process.
     *
     * @throws StatusError
     * @see Process::getStdin()
     */
    public function getStdin(): WritableResourceStream
    {
        return $this->process->getStdin();
    }

    /**
     * Returns the STDOUT stream of the process.
     *
     * @throws StatusError
     * @see Process::getStdout()
     */
    public function getStdout(): ReadableResourceStream
    {
        return $this->process->getStdout();
    }

    /**
     * Returns the STDOUT stream of the process.
     *
     * @throws StatusError
     * @see Process::getStderr()
     */
    public function getStderr(): ReadableResourceStream
    {
        return $this->process->getStderr();
    }

    public function close(): void
    {
        $this->process->kill();
        $this->channel->close();
    }

    public function isClosed(): bool
    {
        return !$this->process->isRunning();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->channel->onClose($onClose);
    }
}
