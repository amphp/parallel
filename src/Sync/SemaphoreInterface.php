<?php
namespace Icicle\Concurrent\Sync;

/**
 * A counting semaphore interface.
 *
 * Objects that implement this interface should guarantee that all operations
 * are atomic.
 */
interface SemaphoreInterface
{
    /**
     * Acquires a lock from the semaphore.
     *
     * Blocks until a lock can be acquired.
     */
    public function acquire();

    /**
     * Releases a lock to the semaphore.
     */
    public function release();
}
