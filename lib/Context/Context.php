<?php

namespace Amp\Parallel\Context;

use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\PanicError;

interface Context extends Channel
{
    /**
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Starts the execution context.
     */
    public function start(): void;

    /**
     * Immediately kills the context.
     */
    public function kill(): void;

    /**
     * @return mixed The data returned from the context.
     *
     * @throws ContextException If the context dies unexpectedly.
     * @throws PanicError If the context throws an uncaught exception.
     */
    public function join(): mixed;
}
