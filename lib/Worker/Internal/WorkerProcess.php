<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\ByteStream;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Process;

/** @internal  */
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
        $this->process->start();

        $stdout = $this->process->getStdout();
        $stdout->unreference();

        $stderr = $this->process->getStderr();
        $stderr->unreference();

        ByteStream\pipe($stdout, ByteStream\getStdout());
        ByteStream\pipe($stderr, ByteStream\getStderr());
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
