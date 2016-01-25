<?php
namespace Icicle\Concurrent\Sync;

/**
 * A non-blocking counting semaphore.
 *
 * Objects that implement this interface should guarantee that all operations
 * are atomic. Implementations do not have to guarantee that acquiring a lock
 * is first-come, first serve.
 */
interface Semaphore extends \Countable
{
    /**
     * Gets the number of currently available locks.
     *
     * @return int The number of available locks.
     */
    public function count(): int;

    /**
     * Gets the total number of locks on the semaphore (not the number of available locks).
     *
     * @return int The total number of locks.
     */
    public function getSize(): int;

    /**
     * @coroutine
     *
     * Acquires a lock from the semaphore asynchronously.
     *
     * If there are one or more locks available, this function resolves immediately with a lock and the lock count is
     * decreased. If no locks are available, the semaphore waits asynchronously for a lock to become available.
     *
     * @return \Generator Resolves with a lock object when the acquire is successful.
     *
     * @resolve \Icicle\Concurrent\Sync\Lock
     */
    public function acquire(): \Generator;
}
