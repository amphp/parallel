<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine or return a promise, can also be a regular function returning a value.
     *
     * @param Environment       $environment Environment instance shared between all Tasks executed on the Worker.
     * @param CancellationToken $token Tasks may safely ignore this parameter if they are not cancellable.
     *
     * @return mixed|Promise|\Generator
     */
    public function run(Environment $environment, CancellationToken $token);
}
