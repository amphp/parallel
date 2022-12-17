<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

class FailingTask implements Task
{
    private string $exceptionType;

    private ?string $previousExceptionType;

    public function __construct(string $exceptionType, ?string $previousExceptionType = null)
    {
        $this->exceptionType = $exceptionType;
        $this->previousExceptionType = $previousExceptionType;
    }

    /**
     * Runs the task inside the caller's context.
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     *
     */
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
    {
        $previous = $this->previousExceptionType ? new $this->previousExceptionType : null;
        throw new $this->exceptionType('Test', 0, $previous);
    }
}
