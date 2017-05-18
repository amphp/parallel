<?php

namespace Amp\Parallel\Process;

use Amp\Coroutine;
use Amp\Parallel\ContextException;
use Amp\Parallel\Process as ProcessContext;
use Amp\Parallel\StatusError;
use Amp\Parallel\Strand;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Parallel\Sync\Internal\ExitResult;
use Amp\Parallel\SynchronizationError;
use Amp\Process\Process;
use Amp\Promise;
use function Amp\call;

class ChannelledProcess implements ProcessContext, Strand {
    /** @var \Amp\Process\Process */
    private $process;

    /** @var \Amp\Parallel\Sync\Channel */
    private $channel;

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
        $this->process->start();
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

        return new Coroutine($this->doReceive());
    }

    private function doReceive() {
        try {
            $data = yield $this->channel->receive();
        } catch (ChannelException $exception) {
            throw new ContextException(
                "The context stopped responding, potentially due to a fatal error or calling exit", 0, $exception
            );
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
                throw new ContextException(
                    "The context went away, potentially due to a fatal error or calling exit", 0, $e
                );
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
        } catch (ChannelException $exception) {
            $this->kill();
            throw new ContextException(
                "The context stopped responding, potentially due to a fatal error or calling exit", 0, $exception
            );
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
