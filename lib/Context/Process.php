<?php

namespace Amp\Parallel\Context;

use Amp\ByteStream;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\Parallel\Sync\ExitResult;
use Amp\Parallel\Sync\SynchronizationError;
use Amp\Process\Process as BaseProcess;
use Amp\Promise;
use function Amp\asyncCall;
use function Amp\call;

class Process implements Context {
    const SCRIPT_PATH = __DIR__ . "/Internal/process-runner.php";

    /** @var string|null External version of SCRIPT_PATH if inside a PHAR. */
    private static $pharScriptPath;

    /** @var string|null Cached path to located PHP binary. */
    private static $binaryPath;

    /** @var \Amp\Process\Process */
    private $process;

    /** @var \Amp\Parallel\Sync\Channel */
    private $channel;

    /**
     * Creates and starts the process at the given path using the optional PHP binary path.
     *
     * @param string|array $script Path to PHP script or array with first element as path and following elements options
     *     to the PHP script (e.g.: ['bin/worker', 'Option1Value', 'Option2Value'].
     * @param string|null $cwd Working directory.
     * @param mixed[] $env Array of environment variables.
     * @param string $binary Path to PHP binary. Null will attempt to automatically locate the binary.
     *
     * @return \Amp\Parallel\Context\Process
     */
    public static function run($script, string $cwd = null, array $env = [], string $binary = null): self {
        $process = new self($script, $cwd, $env, $binary);
        $process->start();
        return $process;
    }

    /**
     * @param string|array $script Path to PHP script or array with first element as path and following elements options
     *     to the PHP script (e.g.: ['bin/worker', 'Option1Value', 'Option2Value'].
     * @param string|null $cwd Working directory.
     * @param mixed[] $env Array of environment variables.
     * @param string $binary Path to PHP binary. Null will attempt to automatically locate the binary.
     *
     * @throws \Error If the PHP binary path given cannot be found or is not executable.
     */
    public function __construct($script, string $cwd = null, array $env = [], string $binary = null) {
        $options = [
            "html_errors" => "0",
            "display_errors" => "0",
            "log_errors" => "1",
        ];

        if (\is_array($script)) {
            $script = \implode(" ", \array_map("escapeshellarg", $script));
        } else {
            $script = \escapeshellarg($script);
        }

        if ($binary === null) {
            if (\PHP_SAPI === "cli") {
                $binary = \PHP_BINARY;
            } else {
                $binary = self::$binaryPath ?? self::locateBinary();
            }
        } elseif (!\is_executable($binary)) {
            throw new \Error(\sprintf("The PHP binary path '%s' was not found or is not executable", $binary));
        }

        // Write process runner to external file if inside a PHAR,
        // because PHP can't open files inside a PHAR directly except for the stub.
        if (\strpos(self::SCRIPT_PATH, "phar://") === 0) {
            if (self::$pharScriptPath) {
                $scriptPath = self::$pharScriptPath;
            } else {
                $contents = \file_get_contents(self::SCRIPT_PATH);
                $contents = \str_replace("__DIR__", \var_export(\dirname(self::SCRIPT_PATH), true), $contents);
                self::$pharScriptPath = $scriptPath = \tempnam(\sys_get_temp_dir(), "amp-process-runner-");
                \file_put_contents($scriptPath, $contents);

                \register_shutdown_function(static function () {
                    @\unlink(self::$pharScriptPath);
                });
            }
        } else {
            $scriptPath = self::SCRIPT_PATH;
        }

        $command = \implode(" ", [
            \escapeshellarg($binary),
            $this->formatOptions($options),
            \escapeshellarg($scriptPath),
            $script,
        ]);

        $this->process = new BaseProcess($command, $cwd, $env);
    }

    private static function locateBinary(): string {
        $executable = \strncasecmp(\PHP_OS, "WIN", 3) === 0 ? "php.exe" : "php";

        $paths = \array_filter(\explode(\PATH_SEPARATOR, \getenv("PATH")));
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

    private function formatOptions(array $options) {
        $result = [];

        foreach ($options as $option => $value) {
            $result[] = \sprintf("-d%s=%s", $option, $value);
        }

        return \implode(" ", $result);
    }

    /**
     * Private method to prevent cloning.
     */
    private function __clone() {
    }

    /**
     * {@inheritdoc}
     */
    public function start() {
        $this->process->start();
        $this->channel = new ChannelledStream($this->process->getStdout(), $this->process->getStdin());

        /** @var ByteStream\ResourceInputStream $childStderr */
        $childStderr = $this->process->getStderr();
        $childStderr->unreference();

        asyncCall(static function () use ($childStderr) {
            $stderr = new ByteStream\ResourceOutputStream(\STDERR);
            yield ByteStream\pipe($childStderr, $stderr);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool {
        return $this->process->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): Promise {
        if ($this->channel === null) {
            throw new StatusError("The process has not been started");
        }

        return call(function () {
            try {
                $data = yield $this->channel->receive();
            } catch (ChannelException $e) {
                throw new ContextException("The context stopped responding, potentially due to a fatal error or calling exit", 0, $e);
            }

            if ($data instanceof ExitResult) {
                $data = $data->getResult();
                throw new SynchronizationError(\sprintf(
                    'Process unexpectedly exited with result of type: %s',
                    \is_object($data) ? \get_class($data) : \gettype($data)
                ));
            }

            return $data;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Promise {
        if ($this->channel === null) {
            throw new StatusError("The process has not been started");
        }

        if ($data instanceof ExitResult) {
            throw new \Error("Cannot send exit result objects");
        }

        return $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function join(): Promise {
        if ($this->channel === null) {
            throw new StatusError("The process has not been started");
        }

        return call(function () {
            try {
                $data = yield $this->channel->receive();
                if (!$data instanceof ExitResult) {
                    throw new SynchronizationError("Did not receive an exit result from process");
                }
            } catch (\Throwable $exception) {
                $this->kill();
                throw $exception;
            }

            $code = yield $this->process->join();
            if ($code !== 0) {
                throw new ContextException(\sprintf("Process exited with code %d", $code));
            }

            return $data->getResult();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        $this->process->kill();
    }
}
