<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;

class FailingTask implements Task
{
    /** @var string */
    private string $exceptionType;

    /** @var string|null */
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
     * @param Cache $cache
     * @param Cancellation $cancellation
     *
     * @return mixed
     */
    public function run(Cache $cache, Cancellation $cancellation): mixed
    {
        $previous = $this->previousExceptionType ? new $this->previousExceptionType : null;
        throw new $this->exceptionType('Test', 0, $previous);
    }
}
