<?php

namespace Amp\Parallel\Context;

use Amp\ByteStream;
use Amp\Coroutine;
use Amp\Parallel\Context;
use Amp\Parallel\ContextException;
use Amp\Parallel\StatusError;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\Parallel\Sync\ExitResult;
use Amp\Parallel\SynchronizationError;
use Amp\Process\Process as BaseProcess;
use Amp\Promise;
use function Amp\asyncCall;
use function Amp\call;

class Process implements Context {
    /** @var string|null Cached path to located PHP binary. */
    private static $binaryPath;

    /** @var \Amp\Process\Process */
    private $process;

    /** @var \Amp\Parallel\Sync\Channel */
    private $channel;

    /**
     * @param string|array $script Path to PHP script or array with first element as path and following elements options
     *     to the PHP script (e.g.: ['bin/worker', '-eOptionValue', '-nOptionValue'].
     * @param string $binary Path to PHP binary. Null will attempt to automatically locate the binary.
     * @param string $cwd Working directory.
     * @param mixed[] $env Array of environment variables.
     */
    public function __construct($script, string $binary = null, string $cwd = "", array $env = []) {
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

        $command = \escapeshellarg($binary) . " " . $this->formatOptions($options) . " " . $script;

        $this->process = new BaseProcess($command, $cwd, $env);
    }

    private static function locateBinary(): string {
        $executable = \strncasecmp(\PHP_OS, "WIN", 3) === 0 ? "php.exe" : "php";
        foreach (\explode(\PATH_SEPARATOR, \getenv("PATH")) as $path) {
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
            $result[] = \sprintf("-d %s=%s", $option, $value);
        }

        return \implode(" ", $result);
    }

    /**
     * Resets process values.
     */
    public function __clone() {
        $this->process = clone $this->process;
        $this->channel = null;
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

        asyncCall(function () use ($childStderr) {
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

        return new Coroutine($this->doReceive());
    }

    private function doReceive() {
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

        return call(function () use ($data) {
            try {
                yield $this->channel->send($data);
            } catch (ChannelException $e) {
                throw new ContextException("The context went away, potentially due to a fatal error or calling exit", 0, $e);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function join(): Promise {
        if ($this->channel === null) {
            throw new StatusError("The process has not been started");
        }

        return new Coroutine($this->doJoin());
    }

    private function doJoin(): \Generator {
        try {
            $data = yield $this->channel->receive();
            if (!$data instanceof ExitResult) {
                throw new SynchronizationError("Did not receive an exit result from process");
            }
        } catch (ChannelException $e) {
            $this->kill();
            throw new ContextException("The context stopped responding, potentially due to a fatal error or calling exit", 0, $e);
        } catch (\Throwable $exception) {
            $this->kill();
            throw $exception;
        }

        $code = yield $this->process->join();
        if ($code !== 0) {
            throw new ContextException(\sprintf("Process exited with code %d", $code));
        }

        return $data->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        $this->process->kill();
    }
}
