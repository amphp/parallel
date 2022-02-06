<?php

namespace Amp\Parallel\Context;

use Amp\Sync\Channel;

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template-extends Channel<TReceive, TSend>
 */
interface Context extends Channel
{
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
