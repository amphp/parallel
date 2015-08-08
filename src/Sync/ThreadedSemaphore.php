<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Coroutine;

/**
 * An asynchronous semaphore based on pthreads' synchronization methods.
 *
 * This is an implementation of a thread-safe semaphore that has non-blocking
 * acquire methods. There is a small tradeoff for asynchronous semaphores; you
 * may not acquire a lock immediately when one is available and there may be a
 * small delay. However, the small delay will not block the thread.
 */
class ThreadedSemaphore extends \Threaded implements SemaphoreInterface
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var int
     */
    private $id = 0;

    /**
     * @var int The number of available locks.
     */
    private $locks = 0;

    /**
     * @var array A queue of lock requests.
     */
    private $waitQueue = [];

    /**
     * Creates a new semaphore.
     *
     * @param int $maxLocks The maximum number of processes that can lock the semaphore.
     */
    public function __construct($maxLocks)
    {
        $this->locks = $maxLocks;
    }

    /**
     * Gets the number of currently available locks.
     *
     * Note that this operation will block the current thread if another thread
     * is acquiring or releasing a lock.
     *
     * @return int The number of available locks.
     */
    public function count()
    {
        return $this->synchronized(function () {
            return $this->locks;
        });
    }

    /**
     * {@inheritdoc}
     *
     * Uses a double locking mechanism to acquire a lock without blocking. A
     * synchronous mutex is used to make sure that the semaphore is queried one
     * at a time to preserve the integrity of the smaphore itself. Then a lock
     * count is used to check if a lock is available without blocking.
     *
     * If a lock is not available, we add the request to a queue and set a timer
     * to check again in the future.
     */
    public function acquire()
    {
        // First, lock a mutex synchronously to prevent corrupting our semaphore
        // data structure.
        $this->lock();

        try {
            // If there are no locks available or the wait queue is not empty,
            // we need to wait our turn to acquire a lock.
            if ($this->locks <= 0 || !empty($this->waitQueue)) {
                // Since there are no free locks that we can claim yet, we need
                // to add our request to the queue of other threads waiting for
                // a free lock to make sure we don't steal one from another
                // thread that has been waiting longer than us.
                $waitId = ++$this->id;
                $this->waitQueue = array_merge($this->waitQueue, [$waitId]);

                // Sleep for a while, unlocking the first lock so that other
                // threads have a chance to release their locks. After we finish
                // sleeping, we can check again to see if it is our turn to acquire
                // a lock.
                do {
                    $this->unlock();
                    yield Coroutine\sleep(self::LATENCY_TIMEOUT);
                    $this->lock();
                } while ($this->locks <= 0 || $this->waitQueue[0] !== $waitId);

                // We have reached our turn, so remove ourselves from the queue.
                $this->waitQueue = array_slice($this->waitQueue, 1);
            }

            // At this point, we have made sure that one of the locks in the
            // semaphore is available for us to use, so decrement the lock count
            // to mark it as taken, and return a lock object that represents the
            // lock just acquired.
            --$this->locks;
            yield new Lock(function (Lock $lock) {
                $this->release();
            });
        } finally {
            // Even if an exception is thrown, we want to unlock our synchronous
            // mutex so we don't bloack any other threads.
            $this->unlock();
        }
    }

    /**
     * Releases a lock from the semaphore.
     */
    protected function release()
    {
        $this->synchronized(function () {
            ++$this->locks;
        });
    }
}
