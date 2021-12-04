<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;

/**
 * A runnable unit of execution.
 *
 * @template TResult
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * @param Environment       $environment Environment instance shared between all Tasks executed on the Worker.
     * @param Cancellation $token Tasks may safely ignore this parameter if they are not cancellable.
     *
     * @return TResult A more specific type can (and should) be declared in implementing classes.
     */
    public function run(Environment $environment, Cancellation $token): mixed;
}
