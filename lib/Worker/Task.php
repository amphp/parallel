<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * @param Environment       $environment Environment instance shared between all Tasks executed on the Worker.
     * @param CancellationToken $token Tasks may safely ignore this parameter if they are not cancellable.
     *
     * @return mixed A more specific type can (and should) be declared in implementing classes.
     */
    public function run(Environment $environment, CancellationToken $token): mixed;
}
