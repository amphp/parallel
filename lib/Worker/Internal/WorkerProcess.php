<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\ByteStream;
use Amp\Cancellation;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\Process;
use Revolt\EventLoop;

/** @internal */
class WorkerProcess implements Context
{
    /** @var Process */
    private Process $process;

    public function __construct($script, array $env = [], string $binary = null)
    {
        $this->process = new Process($script, null, $env, $binary);
    }

    public function receive(?Cancellation $token = null): mixed
    {
        return $this->process->receive($token);
    }

    public function send($data): Future
    {
        return $this->process->send($data);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function start(): void
    {
        $process = $this->process;
        try {
            $process->start();
        } catch (ContextException $e) {
            (function () use ($process) {
                $this->message .= "\nProcess stdout:\n" . ByteStream\buffer($process->getStdout())
                    . "\nProcess stderr:\n" . ByteStream\buffer($process->getStderr());
            })->call($e);
            throw $e;
        }

        EventLoop::queue(function () use ($process): void {
            $stdout = $process->getStdout();
            $stdout->unreference();

            try {
                ByteStream\pipe($stdout, ByteStream\getStdout());
            } catch (\Throwable) {
                $process->kill();
            }
        });

        EventLoop::queue(function () use ($process): void {
            $stderr = $process->getStderr();
            $stderr->unreference();

            try {
                ByteStream\pipe($stderr, ByteStream\getStderr());
            } catch (\Throwable) {
                $process->kill();
            }
        });
    }

    public function kill(): void
    {
        if ($this->process->isRunning()) {
            $this->process->kill();
        }
    }

    public function join(): int
    {
        return $this->process->join();
    }
}
