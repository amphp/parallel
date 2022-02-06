<?php

namespace Amp\Parallel\Worker;

use Amp\Future;
use Amp\Sync\Channel;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template TCache
 */
final class Job
{
    /**
     * @param Task<TResult, TReceive, TSend, TCache> $task
     * @param Channel<TSend, TReceive> $channel
     * @param Future<TResult> $future
     */
    public function __construct(
        private Task $task,
        private Channel $channel,
        private Future $future,
    ) {
    }

    /**
     * @return Task<TResult, TReceive, TSend>
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * @return Channel<TSend, TReceive>
     */
    public function getChannel(): Channel
    {
        return $this->channel;
    }

    /**
     * Returns a cloned object with the given channel.
     *
     * @param Channel<TSend, TReceive> $channel
     *
     * @return Job<TResult, TReceive, TSend, TCache>
     */
    public function withChannel(Channel $channel): self
    {
        $clone = clone $this;
        $clone->channel = $channel;
        return $clone;
    }

    /**
     * @return Future<TResult>
     */
    public function getFuture(): Future
    {
        return $this->future;
    }

    /**
     * Returns a cloned object with the given Future.
     *
     * @param Future<TResult> $future
     *
     * @return Job<TResult, TReceive, TSend, TCache>
     */
    public function withFuture(Future $future): self
    {
        $clone = clone $this;
        $clone->future = $future;
        return $clone;
    }
}
