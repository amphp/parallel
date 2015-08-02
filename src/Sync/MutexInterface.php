<?php
namespace Icicle\Concurrent\Sync;

/**
 * A simple mutex that provides synchronous, atomic locking and unlocking across
 * contexts.
 *
 * Objects that implement this interface should guarantee that all operations
 * are atomic.
 */
interface MutexInterface
{
    /**
     * Locks the mutex.
     */
    public function lock();

    /**
     * Unlocks the mutex.
     */
    public function unlock();
}
