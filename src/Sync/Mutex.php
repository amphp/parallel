<?php
namespace Icicle\Concurrent\Sync;

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
     * @return \Generator Resolves with a lock object when the acquire is successful.
     *
     * @resolve \Icicle\Concurrent\Sync\Lock
     */
    public function acquire();
}
