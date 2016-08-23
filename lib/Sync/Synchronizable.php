<?php declare(strict_types = 1);

namespace Amp\Parallel\Sync;

use Interop\Async\Awaitable;

/**
 * An object that can be synchronized for exclusive access across contexts.
 */
interface Synchronizable {
    /**
     * Asynchronously invokes a callback while maintaining an exclusive lock on the object.
     *
     * The arguments passed to the callback depend on the implementing object. If the callback throws an exception,
     * the lock on the object will be immediately released.
     *
     * @param callable<(mixed ...$args): \Generator|mixed> $callback The synchronized callback to invoke.
     *     The callback may be a regular function or a coroutine.
     *
     * @return \Interop\Async\Awaitable<mixed> Resolves with the return value of $callback or fails if $callback
     *     throws an exception.
     */
    public function synchronized(callable $callback): Awaitable;
}
