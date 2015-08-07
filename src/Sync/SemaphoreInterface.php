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
     * Acquires a lock from the semaphore asynchronously.
     *
     * @return \Icicle\Promise\PromiseInterface<Lock> A promise resolved with a lock.
     */
    public function acquire();
}
