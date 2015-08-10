<?php
namespace Icicle\Concurrent\Sync;

/**
 * A simple mutex that provides asynchronous, atomic locking and unlocking across
 * contexts.
 *
 * Objects that implement this interface should guarantee that all operations
 * are atomic. Implementations do not have to guarantee that acquiring a lock
 * is first-come, first serve.
 */
interface MutexInterface
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
