<?php

namespace Amp\Parallel\Process;

use Amp\{ Coroutine, Deferred, Emitter, Failure, Stream, Success };
use Amp\Parallel\{ ContextException, Process as ProcessContext, StatusError };
use AsyncInterop\{ Loop, Promise };

class StreamedProcess implements ProcessContext {
    const CHUNK_SIZE = 8192;

    /** @var \Amp\Parallel\Process\Process */
    private $process;

    /** @var \Amp\Emitter Emits bytes read from STDOUT. */
    private $stdoutEmitter;

    /** @var \Amp\Emitter Emits bytes read from STDERR. */
    private $stderrEmitter;

    /** @var string|null */
    private $stdinWatcher;

    /** @var string|null */
    private $stdoutWatcher;

    /** @var string|null */
    private $stderrWatcher;

    /** @var \SplQueue Queue of data to write to STDIN. */
    private $writeQueue;

    /** @var \AsyncInterop\Promise Promise resolved when process ends. */
    private $promise;

    /**
     * @param   string $command Command to run.
     * @param   string|null $cwd Working directory or use an empty string to use the working directory of the current
     *     PHP process.
     * @param   mixed[] $env Environment variables or use an empty array to inherit from the current PHP process.
     * @param   mixed[] $options Options for proc_open().
     */
    public function __construct(string $command, string $cwd = null, array $env = [], array $options = []) {
        $this->process = new Process($command, $cwd, $env, $options);
        $this->stdoutEmitter = new Emitter;
        $this->stderrEmitter = new Emitter;
        $this->writeQueue = new \SplQueue;
    }

    /**
     * Resets process values.
     */
    public function __clone() {
        $this->process = clone $this->process;
        $this->stdinWatcher = null;
        $this->stdoutWatcher = null;
        $this->stderrWatcher = null;
        $this->stdoutEmitter = new Emitter;
        $this->stderrEmitter = new Emitter;
        $this->writeQueue = new \SplQueue;
        $this->promise = null;
    }

    /**
     * {@inheritdoc}
     */
    public function start() {
        $this->process->start();

        $writes = $this->writeQueue;
        $this->stdinWatcher = Loop::onWritable($this->process->getStdIn(), static function ($watcher, $resource) use ($writes) {
            while (!$writes->isEmpty()) {
                /** @var \Amp\Deferred $deferred */
                list($data, $previous, $deferred) = $writes->shift();
                $length = \strlen($data);

                if ($length === 0) {
                    $deferred->resolve(0);
                    continue;
                }

                // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
                $written = @\fwrite($resource, $data);

                if ($written === false || $written === 0) {
                    $message = "Failed to write to STDIN";
                    if ($error = \error_get_last()) {
                        $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                    }
                    $deferred->fail(new ContextException($message));
                    return;
                }

                if ($length <= $written) {
                    $deferred->resolve($written + $previous);
                    continue;
                }

                $data = \substr($data, $written);
                $writes->unshift([$data, $written + $previous, $deferred]);
                return;
            }
        });
        Loop::disable($this->stdinWatcher);

        $callback = static function ($watcher, $resource, Emitter $emitter) {
            // Error reporting suppressed since fread() produces a warning if the stream unexpectedly closes.
            if (@\feof($resource) || ($data = @\fread($resource, self::CHUNK_SIZE)) === false) {
                Loop::disable($watcher);
                return;
            }

            if ($data !== "") {
                $emitter->emit($data);
            }
        };

        $this->stdoutWatcher = Loop::onReadable($this->process->getStdOut(), $callback, $this->stdoutEmitter);
        $this->stderrWatcher = Loop::onReadable($this->process->getStdErr(), $callback, $this->stderrEmitter);

        $this->promise = $this->process->join();
        $this->promise->when(function (\Throwable $exception = null, int $code = null) {
            Loop::cancel($this->stdinWatcher);
            Loop::cancel($this->stdoutWatcher);
            Loop::cancel($this->stderrWatcher);

            if ($exception) {
                $this->stdoutEmitter->fail($exception);
                $this->stderrEmitter->fail($exception);
                return;
            }

            $this->stdoutEmitter->resolve($code);
            $this->stderrEmitter->resolve($code);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool {
        return $this->process->isRunning();
    }

    /**
     * @param string $data
     *
     * @return \AsyncInterop\Promise
     */
    public function write(string $data): Promise {
        $length = \strlen($data);
        $written = 0;

        if ($this->writeQueue->isEmpty()) {
            if ($length === 0) {
                return new Success(0);
            }

            // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
            $written = @\fwrite($this->process->getStdIn(), $data, self::CHUNK_SIZE);

            if ($written === false) {
                $message = "Failed to write to stream";
                if ($error = \error_get_last()) {
                    $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                }
                return new Failure(new ContextException($message));
            }

            if ($length <= $written) {
                return new Success($written);
            }

            $data = \substr($data, $written);
        }

        return new Coroutine($this->doWrite($data, $written));
    }

    private function doWrite(string $data, int $written): \Generator {
        $deferred = new Deferred;
        $this->writeQueue->push([$data, $written, $deferred]);

        Loop::enable($this->stdinWatcher);

        try {
            $written = yield $deferred->promise();
        } catch (\Throwable $exception) {
            $this->kill();
            throw $exception;
        } finally {
            if ($this->writeQueue->isEmpty()) {
                Loop::disable($this->stdinWatcher);
            }
        }

        return $written;
    }

    public function getStdOut(): Stream {
        return $this->stdoutEmitter->stream();
    }

    public function getStdErr(): Stream {
        return $this->stderrEmitter->stream();
    }

    /**
     * {@inheritdoc}
     */
    public function join(): Promise {
        if ($this->promise === null) {
            throw new StatusError("The process has not been started");
        }

        return $this->promise;
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        $this->process->kill();
    }

    /**
     * {@inheritdoc}
     */
    public function getPid(): int {
        return $this->process->getPid();
    }

    /**
     * {@inheritdoc}
     */
    public function signal(int $signo) {
        $this->process->signal($signo);
    }
}
