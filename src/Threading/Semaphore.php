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
     * @var Internal\Semaphore
     */
    private $semaphore;

    /**
     * @var int
     */
    private $maxLocks;

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $locks The maximum number of locks that can be acquired from the semaphore.
     */
    public function __construct($locks)
    {
        $this->init($locks);
    }

    /**
     * Initializes the semaphore with a given number of locks.
     *
     * @param int $locks
     */
    private function init($locks)
    {
        $locks = (int) $locks;
        if ($locks < 1) {
            $locks = 1;
        }

        $this->semaphore = new Internal\Semaphore($locks);
        $this->maxLocks = $locks;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->semaphore->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return $this->maxLocks;
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        return $this->semaphore->acquire();
    }

    /**
     * Clones the semaphore, creating a new instance with the same number of locks, all available.
     */
    public function __clone()
    {
        $this->init($this->getSize());
    }
}
