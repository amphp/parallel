<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\CancellationToken;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use function Amp\asyncValue;

class TestTask implements Task
{
    private mixed $returnValue;
    private int $delay;

    public function __construct(mixed $returnValue, int $delay = 0)
    {
        $this->returnValue = $returnValue;
        $this->delay = $delay;
    }

    public function run(Environment $environment, CancellationToken $token): mixed
    {
        if ($this->delay) {
            return asyncValue($this->delay, $this->returnValue);
        }

        return $this->returnValue;
    }
}
