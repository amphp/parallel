<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\ByteStream;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Process;
use Amp\Promise;
use function Amp\call;

class WorkerProcess implements Context
{
    /** @var Process */
    private $process;

    public function __construct($script, array $env = [], string $binary = null)
    {
        $this->process = new Process($script, null, $env, $binary);
    }

    public function receive(): Promise
    {
        return $this->process->receive();
    }

    public function send($data): Promise
    {
        return $this->process->send($data);
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function start(): Promise
    {
        return call(function () {
            $result = yield $this->process->start();

            ByteStream\pipe($this->process->getStdout(), ByteStream\getStdout());
            ByteStream\pipe($this->process->getStderr(), ByteStream\getStderr());

            return $result;
        });
    }

    public function kill()
    {
        $this->process->kill();
    }

    public function join(): Promise
    {
        return $this->process->join();
    }
}
