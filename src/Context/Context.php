<?php

namespace Amp\Parallel\Context;

use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Context\ContextPanicError;

/**
 * @template TValue
 * @template-extends Channel<TValue>
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
     * @return mixed The data returned from the context.
     *
     * @throws ContextException If the context dies unexpectedly.
     * @throws ContextPanicError If the context throws an uncaught exception.
     */
    public function join(): mixed;
}
