<?php
namespace Icicle\Concurrent\Threading\Internal;

use Icicle\Concurrent\Sync\Lock;
use Icicle\Coroutine;

/**
 * An asynchronous semaphore based on pthreads' synchronization methods.
 *
 * @internal
 */
class Semaphore extends \Threaded
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var int
     */
    private $nextId = 0;

    /**
     * @var int The number of available locks.
     */
    private $locks;

    /**
     * @var array A queue of lock requests.
     */
    private $waitQueue = [];

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $maxLocks The maximum number of locks that can be acquired from the semaphore.
     */
    public function __construct($maxLocks)
    {
        $this->locks = (int) $maxLocks;
        if ($this->locks < 1) {
            $this->locks = 1;
        }
    }

    /**
     * Gets the number of currently available locks.
     *
     * @return int The number of available locks.
     */
    public function count()
    {
        return $this->locks;
    }

    /**
     * Uses a double locking mechanism to acquire a lock without blocking. A
     * synchronous mutex is used to make sure that the semaphore is queried one
     * at a time to preserve the integrity of the semaphore itself. Then a lock
     * count is used to check if a lock is available without blocking.
     *
     * If a lock is not available, we add the request to a queue and set a timer
     * to check again in the future.
     */
    public function acquire()
    {
        $tsl = function () use (&$waitId) {
            // If there are no locks available or the wait queue is not empty,
            // we need to wait our turn to acquire a lock.
            if ($this->locks > 0 && empty($this->waitQueue)) {
                --$this->locks;
                return false;
            }

            // Since there are no free locks that we can claim yet, we need
            // to add our request to the queue of other threads waiting for
            // a free lock to make sure we don't steal one from another
            // thread that has been waiting longer than us.
            $waitId = $this->nextId++;
            $this->waitQueue = array_merge($this->waitQueue, [$waitId]);

            return true;
        };

        if ($this->synchronized($tsl)) {
            $tsl = function () use (&$waitId) {
                if ($this->locks > 0 && $this->waitQueue[0] === $waitId) {
                    // At this point, we have made sure that one of the locks in the
                    // semaphore is available for us to use, so decrement the lock count
                    // to mark it as taken, and return a lock object that represents the
                    // lock just acquired.
                    --$this->locks;
                    $this->waitQueue = array_slice($this->waitQueue, 1);
                    return false;
                }

                return true;
            };

            do {
                yield Coroutine\sleep(self::LATENCY_TIMEOUT);
            } while ($this->synchronized($tsl));
        }

        yield new Lock(function () {
            $this->release();
        });
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
