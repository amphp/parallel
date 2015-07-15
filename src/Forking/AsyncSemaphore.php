<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\SemaphoreException;
use Icicle\Concurrent\Semaphore;
use Icicle\Loop;
use Icicle\Promise;

/**
 * An asynchronous semaphore with non-blocking lock requests.
 *
 * To keep in sync with all handles to the async semaphore, a synchronous
 * semaphore is used as a gatekeeper to access the lock count; such locks are
 * guaranteed to perform very few memory read or write operations to reduce the
 * semaphore latency.
 */
class AsyncSemaphore extends SharedObject
{
    /**
     * @var int The number of available locks.
     * @synchronized
     */
    private $locks;

    /**
     * @var int The maximum number of locks the semaphore allows.
     * @synchronized
     */
    private $maxLocks;

    /**
     * @var int A queue of processes waiting on locks.
     * @synchronized
     */
    private $processQueue;

    /**
     * @var \SplQueue A queue of promises waiting to acquire a lock within the
     *                current calling context.
     */
    private $waitQueue;

    /**
     * @var Semaphore A synchronous semaphore for double locking.
     */
    private $semaphore;

    /**
     * Creates a new asynchronous semaphore.
     *
     * @param int $maxLocks The maximum number of processes that can lock the semaphore.
     */
    public function __construct($maxLocks = 1)
    {
        parent::__construct();

        if (!is_int($maxLocks) || $maxLocks < 1) {
            throw new InvalidArgumentError('Max locks must be a positive integer.');
        }

        $this->locks = $maxLocks;
        $this->maxLocks = $maxLocks;
        $this->processQueue = new \SplQueue();
        $this->waitQueue = new \SplQueue();
        $this->semaphore = new Semaphore(1);

        Loop\signal(SIGUSR1, function () {
            $this->handlePendingLocks();
        });
    }

    /**
     * Acquires a lock from the semaphore.
     *
     * @return PromiseInterface A promise resolved when a lock has been acquired.
     *
     * If there are one or more locks available, the returned promise is resolved
     * immediately and the lock count is decreased. If no locks are available,
     * the semaphore waits asynchronously for an unlock signal from another
     * process before resolving.
     */
    public function acquire()
    {
        $deferred = new Promise\Deferred();

        // Alright, we gotta get in and out as fast as possible. Deep breath...
        $this->semaphore->acquire();

        try {
            if ($this->locks > 0) {
                // Oh goody, a free lock! Acquire a lock and get outta here!
                --$this->locks;
                $deferred->resolve();
            } else {
                $this->waitQueue->enqueue($deferred);
                $this->processQueue->enqueue(getmypid());
                $this->__writeSynchronizedProperties();
            }
        } finally {
            $this->semaphore->release();
        }

        return $deferred->getPromise();
    }

    /**
     * Releases a lock to the semaphore.
     *
     * @return PromiseInterface A promise resolved when a lock has been released.
     *
     * Note that this function is near-atomic and returns almost immediately. A
     * promise is returned only for consistency.
     */
    public function release()
    {
        $this->semaphore->acquire();

        if ($this->locks === $this->maxLocks) {
            $this->semaphore->release();
            throw new SemaphoreException('No locks acquired to release.');
        }

        ++$this->locks;

        if (!$this->processQueue->isEmpty()) {
            $pid = $this->processQueue->dequeue();
            $pending = true;
        }

        $this->semaphore->release();

        if ($pending) {
            if ($pid === getmypid()) {
                $this->waitQueue->dequeue()->resolve();
            } else {
                posix_kill($pid, SIGUSR1);
            }
        }

        return Promise\resolve();
    }

    /**
     * Handles pending lock requests and resolves a pending acquire() call if
     * new locks are available.
     */
    private function handlePendingLocks()
    {
        $dequeue = false;
        $this->semaphore->acquire();
        if ($this->locks > 0 && !$this->waitQueue->isEmpty()) {
            --$this->locks;
            $dequeue = true;
        }
        $this->semaphore->release();

        if ($dequeue) {
            $this->waitQueue->dequeue()->resolve();
        }
    }

    public function destroy()
    {
        parent::destroy();
        $this->semaphore->destroy();
    }
}
