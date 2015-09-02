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
     * @var int The number of available locks.
     */
    private $locks;

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $locks The maximum number of locks that can be acquired from the semaphore.
     */
    public function __construct($locks)
    {
        $this->locks = (int) $locks;
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
        $tsl = function () {
            // If there are no locks available or the wait queue is not empty,
            // we need to wait our turn to acquire a lock.
            if ($this->locks > 0) {
                --$this->locks;
                return false;
            }
            return true;
        };

        while ($this->locks < 1 || $this->synchronized($tsl)) {
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
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
