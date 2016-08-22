<?php declare(strict_types = 1);

namespace Amp\Concurrent;

use Interop\Async\Awaitable;

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
     * @return \Interop\Async\Awaitable<mixed> Resolves with the returned from the context.
     */
    public function join(): Awaitable;
}
