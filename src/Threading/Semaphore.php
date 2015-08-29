<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\SemaphoreInterface;

/**
 * An asynchronous semaphore based on pthreads' synchronization methods.
 *
 * This is an implementation of a thread-safe semaphore that has non-blocking
 * acquire methods. There is a small tradeoff for asynchronous semaphores; you
 * may not acquire a lock immediately when one is available and there may be a
 * small delay. However, the small delay will not block the thread.
 */
class Semaphore implements SemaphoreInterface
{
    /**
     * @var \Icicle\Concurrent\Threading\Internal\Semaphore
     */
    private $semaphore;

    /**
     * @param int $maxLocks
     */
    public function __construct($maxLocks)
    {
        $this->semaphore = new Internal\Semaphore($maxLocks);
    }

    /**
     * Gets the number of currently available locks.
     *
     * @return int The number of available locks.
     */
    public function count()
    {
        return $this->semaphore->count();
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        return $this->semaphore->acquire();
    }
}