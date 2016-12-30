<?php

namespace Amp\Parallel\Sync;

use Interop\Async\Promise;

/**
 * A non-blocking synchronization primitive that can be used for mutual exclusion across contexts.
 *
 * Objects that implement this interface should guarantee that all operations
 * are atomic. Implementations do not have to guarantee that acquiring a lock
 * is first-come, first serve.
 */
interface Mutex
{
    /**
     * @coroutine
     *
     * Acquires a lock on the mutex.
     *
     * @return \Interop\Async\Promise<\Amp\Parallel\Sync\Lock> Resolves with a lock object when the acquire is
     * successful.
     */
    public function acquire(): Promise;
}
