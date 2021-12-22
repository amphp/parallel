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
     * Returns a cloned object with the given channel.
     *
     * @param Channel<TReceive, TSend> $channel
     *
     * @return Job<TResult, TReceive, TSend>
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
     * @return Job<TResult, TReceive, TSend>
     */
    public function withFuture(Future $future): self
    {
        $clone = clone $this;
        $clone->future = $future;
        return $clone;
    }
}
