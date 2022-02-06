<?php

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-extends Channel<TReceive, TSend>
 */
interface Context //extends Channel
{
    /**
     * @param Cancellation|null $cancellation Cancels waiting for the next value. Note the next value is not discarded
     * if the operation is cancelled, rather it will be returned from the next call to this method.
     *
     * @return TReceive Data received.
     *
     * @throws ChannelException If receiving from the channel fails or the channel closed.
     */
    public function receive(?Cancellation $cancellation = null): mixed;

    /**
     * @param TSend $data
     *
     * @throws ChannelException If sending on the channel fails or the channel is already closed.
     */
    public function send(mixed $data): void;

    /**
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Immediately kills the context.
     */
    public function kill(): void;

    /**
     * @return TResult The data returned from the context.
     *
     * @throws ContextException If the context dies unexpectedly.
     * @throws ContextPanicError If the context throws an uncaught exception.
     */
    public function join(): mixed;
}
