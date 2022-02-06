<?php

namespace Amp\Parallel\Context;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Parallel\Sync\IpcHub;
use Amp\Parallel\Sync\SynchronizationError;
use Amp\Process\Process;
use Amp\Process\ProcessException;
use Amp\Sync\ChannelException;
use Amp\Sync\StreamChannel;
use Amp\TimeoutCancellation;
use function Amp\async;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-implements Context<TResult, TReceive, TSend>
 */
final class ProcessContext implements Context
{
    private const SCRIPT_PATH = __DIR__ . "/Internal/process-runner.php";
    public const DEFAULT_START_TIMEOUT = 5;

    /** @var string|null External version of SCRIPT_PATH if inside a PHAR. */
    private static ?string $pharScriptPath = null;

    /** @var string|null PHAR path with a '.phar' extension. */
    private static ?string $pharCopy = null;

    /** @var string|null Cached path to located PHP binary. */
    private static ?string $binaryPath = null;

    /**
     * @param string|string[] $script Path to PHP script or array with first element as path and following elements
     *     options to the PHP script (e.g.: ['bin/worker.php', 'Option1Value', 'Option2Value']).
     * @param string|null $workingDirectory Working directory.
     * @param string[] $environment Array of environment variables.
     * @param Cancellation|null $cancellation
     * @param string|null $binaryPath Path to PHP binary. Null will attempt to automatically locate the binary.
     * @param IpcHub|null $ipcHub Optional IpcHub instance.
     *
     * @return ProcessContext<TResult, TReceive, TSend>
     *
     * @throws ContextException If starting the process fails.
     */
    public static function start(
        string|array $script,
        string $workingDirectory = null,
        array $environment = [],
        ?Cancellation $cancellation = null,
        ?string $binaryPath = null,
        ?IpcHub $ipcHub = null
    ): self {
        $ipcHub ??= ipcHub();

        $options = [
            "html_errors" => "0",
            "display_errors" => "0",
            "log_errors" => "1",
        ];

        if ($binaryPath === null) {
            if (\PHP_SAPI === "cli") {
                $binaryPath = \PHP_BINARY;
            } else {
                $binaryPath = self::$binaryPath ?? self::locateBinary();
            }
        } elseif (!\is_executable($binaryPath)) {
            throw new \Error(\sprintf("The PHP binary path '%s' was not found or is not executable", $binaryPath));
        }

        // Write process runner to external file if inside a PHAR,
        // because PHP can't open files inside a PHAR directly except for the stub.
        if (\str_starts_with(self::SCRIPT_PATH, "phar://")) {
            if (self::$pharScriptPath) {
                $scriptPath = self::$pharScriptPath;
            } else {
                $path = \dirname(self::SCRIPT_PATH);

                if (!str_ends_with(\Phar::running(false), ".phar")) {
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
            if (isset(self::$pharCopy) && \is_array($script) && isset($script[0])) {
                $script[0] = "phar://" . self::$pharCopy . \substr($script[0], \strlen(\Phar::running(true)));
            }
        } else {
            $scriptPath = self::SCRIPT_PATH;
        }

        $key = $ipcHub->generateKey();

        $command = \array_merge(
            [$binaryPath],
            self::formatOptions($options),
            [$scriptPath],
            [$ipcHub->getUri()],
            [(string) \strlen($key)],
            \is_array($script) ? $script : [$script],
        );

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
            throw new ContextException("Starting the process failed", 0, $exception);
        }

        return new self($process, $channel);
    }

    private static function locateBinary(): string
    {
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
                return self::$binaryPath = $path;
            }
        }

        throw new \Error("Could not locate PHP executable binary");
    }

    private static function formatOptions(array $options): array
    {
        $result = [];

        foreach ($options as $option => $value) {
            $result[] = \sprintf("-d%s=%s", $option, $value);
        }

        return $result;
    }

    /**
     * @param Process $process
     * @param StreamChannel<TReceive, TSend> $channel
     */
    private function __construct(
        private Process $process,
        private StreamChannel $channel,
    ) {
    }

    /**
     * Always throws to prevent cloning.
     */
    public function __clone()
    {
        throw new \Error(self::class . ' objects cannot be cloned');
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        try {
            $data = $this->channel->receive($cancellation);
        } catch (ChannelException $e) {
            throw new ContextException("The process stopped responding, potentially due to a fatal error or calling exit", 0, $e);
        }

        if ($data === null) {
            throw new ContextException("The channel closed when receiving data from the process");
        }

        if ($data instanceof Internal\ExitResult) {
            $data = $data->getResult();
            throw new SynchronizationError(\sprintf(
                'Process unexpectedly exited with result of type: %s',
                get_debug_type($data),
            ));
        }

        if (!$data instanceof Internal\ContextMessage) {
            throw new SynchronizationError(\sprintf(
                'Unexpected data type from context: %s',
                get_debug_type($data),
            ));
        }

        return $data->getMessage();
    }

    public function send(mixed $data): void
    {
        try {
            $this->channel->send($data);
        } catch (ChannelException $e) {
            if ($this->channel->isClosed()) {
                throw new ContextException("The process stopped responding, potentially due to a fatal error or calling exit", 0, $e);
            }

            try {
                $data = async(fn () => $this->join())->await(new TimeoutCancellation(0.1));
            } catch (ContextException|ChannelException|CancelledException) {
                if ($this->isRunning()) {
                    $this->kill();
                }
                throw new ContextException("The process stopped responding, potentially due to a fatal error or calling exit", 0, $e);
            }

            throw new SynchronizationError(\sprintf(
                'Process unexpectedly exited with result of type: %s',
                get_debug_type($data),
            ), 0, $e);
        }
    }

    /**
     * @return TResult
     * @throws ContextException
     */
    public function join(): mixed
    {
        try {
            $data = $this->channel->receive();
        } catch (\Throwable $exception) {
            if ($this->isRunning()) {
                $this->kill();
            }
            throw new ContextException("Failed to receive result from process", 0, $exception);
        }

        if ($data === null) {
            throw new ContextException("Failed to receive result from process");
        }

        if (!$data instanceof Internal\ExitResult) {
            if ($this->isRunning()) {
                $this->kill();
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
     * @param int $signo
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
     * @return int
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
     * @return WritableResourceStream
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
     * @return ReadableResourceStream
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
     * @return ReadableResourceStream
     *
     * @throws StatusError
     * @see Process::getStderr()
     */
    public function getStderr(): ReadableResourceStream
    {
        return $this->process->getStderr();
    }

    public function kill(): void
    {
        $this->process->kill();
        $this->channel->close();
    }
}
