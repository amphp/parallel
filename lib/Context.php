<?php

namespace Amp\Parallel;

use AsyncInterop\Promise;

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
     * @return \AsyncInterop\Promise<mixed> Resolves with the returned from the context.
     */
    public function join(): Promise;
}
