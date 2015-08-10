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
     * @coroutine
     *
     * Acquires a lock from the semaphore asynchronously.
     *
     * @return \Generator
     *
     * @resolve \Icicle\Concurrent\Sync\Lock
     */
    public function acquire();
}
