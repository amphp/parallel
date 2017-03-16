<?php

namespace Amp\Parallel;

use Amp\Promise;

interface Context {
    /**
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Starts the execution context.
     */
    public function start();

    /**
     * Immediately kills the context.
     */
    public function kill();

    /**
     * @return \Amp\Promise<mixed> Resolves with the returned from the context.
     *
     * @throws \Amp\Parallel\ContextException If the context dies unexpectedly.
     * @throws \Amp\Parallel\PanicError If the context throws an uncaught exception.
     */
    public function join(): Promise;
}
