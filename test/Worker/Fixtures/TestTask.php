<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cancellation;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use function Amp\delay;

class TestTask implements Task
{
    private mixed $returnValue;
    private float $delay;

    public function __construct(mixed $returnValue, float $delay = 0)
    {
        $this->returnValue = $returnValue;
        $this->delay = $delay;
    }

    public function run(Environment $environment, Cancellation $token): mixed
    {
        if ($this->delay) {
            delay($this->delay);
        }

        return $this->returnValue;
    }
}
