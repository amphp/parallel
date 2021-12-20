<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Sync\Channel;

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
     * @param Channel $channel Communication channel to parent process.
     * @param Cache $cache Cache instance shared between all Tasks executed on the Worker.
     * @param Cancellation $cancellation Tasks may safely ignore this parameter if they are not cancellable.
     *
     * @return TResult A more specific type can (and should) be declared in implementing classes.
     */
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed;
}
