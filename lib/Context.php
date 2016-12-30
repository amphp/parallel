<?php

namespace Amp\Parallel;

use Interop\Async\Promise;

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
     * @return \Interop\Async\Promise<mixed> Resolves with the returned from the context.
     */
    public function join(): Promise;
}
