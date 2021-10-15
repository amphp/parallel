<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\ByteStream;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Process;
use function Revolt\launch;

/** @internal */
class WorkerProcess implements Context
{
    /** @var Process */
    private Process $process;

    public function __construct($script, array $env = [], string $binary = null)
    {
        $this->process = new Process($script, null, $env, $binary);
    }

    public function receive(): mixed
    {
        return $this->process->receive();
    }

    public function send($data): void
    {
        $this->process->send($data);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function start(): void
    {
        $process = $this->process;
        $process->start();

        launch(function () use ($process): void {
            $stdout = $process->getStdout();
            $stdout->unreference();

            try {
                ByteStream\pipe($stdout, ByteStream\getStdout());
            } catch (\Throwable) {
                $process->kill();
            }
        });

        launch(function () use ($process): void {
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
