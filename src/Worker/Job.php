<?php

namespace Amp\Parallel\Worker;

use Amp\Future;
use Amp\Parallel\Sync\Channel;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 */
final class Job
{
    public function __construct(
        private Task $task,
        private Channel $channel,
        private Future $future,
    ) {
    }

    /**
     * @return Task<TResult>
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * @return Channel<TReceive, TSend>
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }

    /**
     * @return Future<TResult>
     */
    public function getFuture(): Future
    {
        return $this->future;
    }
}
