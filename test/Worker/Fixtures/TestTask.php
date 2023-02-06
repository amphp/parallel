<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
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

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        if ($this->delay) {
            delay($this->delay);
        }

        return $this->returnValue;
    }
}
