<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\CancellationToken;
use Amp\Delayed;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class TestTask implements Task
{
    private $returnValue;
    private $delay = 0;

    public function __construct($returnValue, int $delay = 0)
    {
        $this->returnValue = $returnValue;
        $this->delay = $delay;
    }

    public function run(Environment $environment, CancellationToken $token)
    {
        if ($this->delay) {
            return new Delayed($this->delay, $this->returnValue);
        }

        return $this->returnValue;
    }
}
