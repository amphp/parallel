<?php

namespace Amp\Parallel\Process;

use Amp\Coroutine;
use Amp\Parallel\{ ContextException, Process as ProcessContext, StatusError, Strand, SynchronizationError };
use Amp\Parallel\Sync\{ ChannelledSocket, Internal\ExitStatus };
use Amp\Process\Process;
use AsyncInterop\Promise;

class ChannelledProcess implements ProcessContext, Strand {
    /** @var \Amp\Process\Process */
    private $process;

    /** @var \Amp\Parallel\Sync\Channel */
    private $channel;

    /** @var \AsyncInterop\Promise */
    private $promise;

    /**
     * @param string $path Path to PHP script.
     * @param string $cwd Working directory.
     * @param mixed[] $env Array of environment variables.
     */
    public function __construct(string $path, string $cwd = "", array $env = []) {
        $command = \PHP_BINARY . " " . \escapeshellarg($path);
        $this->process = new Process($command, $cwd, $env);
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
        $this->promise = $this->process->execute();
        $this->channel = new ChannelledSocket($this->process->getStdOut(), $this->process->getStdIn(), false);
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

        return \Amp\pipe($this->channel->receive(), static function ($data) {
            if ($data instanceof ExitStatus) {
                $data = $data->getResult();
                throw new SynchronizationError(\sprintf(
                    "Process unexpectedly exited with result of type: %s",
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

        if ($data instanceof ExitStatus) {
            throw new \Error("Cannot send exit status objects");
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

        return new Coroutine($this->doJoin());
    }

    private function doJoin(): \Generator {
        try {
            $data = yield $this->channel->receive();
            if (!$data instanceof ExitStatus) {
                throw new SynchronizationError("Did not receive an exit status from process");
            }
        } catch (\Throwable $exception) {
            $this->kill();
            throw $exception;
        }

        $code = yield $this->promise;
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
