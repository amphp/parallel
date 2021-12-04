<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cancellation;
use Amp\Parallel\Worker\Environment;
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
     * @param Environment       $environment
     * @param Cancellation $token
     *
     * @return mixed
     */
    public function run(Environment $environment, Cancellation $token): mixed
    {
        $previous = $this->previousExceptionType ? new $this->previousExceptionType : null;
        throw new $this->exceptionType('Test', 0, $previous);
    }
}
