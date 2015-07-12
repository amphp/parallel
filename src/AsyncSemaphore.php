<?php
namespace Icicle\Concurrent;

use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Forking\Synchronized;
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
class AsyncSemaphore extends Synchronized
{
    /**
     * @var \SplQueue A queue of promises waiting to acquire a lock within the
     *                current calling context.
     */
    private $waitQueue;

    /**
     * @synchronized
     */
    private $maxLocks;

    /**
     * @synchronized
     */
    private $queueSize;

    /**
     * @synchronized
     */
    private $locks;

    /**
     * @synchronized
     */
    private $processQueue;

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

        $this->maxLocks = $maxLocks;
        $this->waitQueue = new \SplQueue();
        $this->queueSize = 0;
        $this->locks = $maxLocks;
        $this->processQueue = new \SplQueue();

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
        print "Lock request\n";
        // Alright, we gotta get in and out as fast as possible. Deep breath...
        return $this->synchronized(function () {
            if ($this->locks > 0) {
                // Oh goody, a free lock! Acquire a lock and get outta here!
                --$this->locks;
                return Promise\resolve();
            } else {
                $deferred = new Promise\Deferred();
                $this->waitQueue->enqueue($deferred);
                $this->processQueue->enqueue(getmypid());
                return $deferred->getPromise();
            }
        });
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
        $this->synchronized(function () {
            if ($this->locks === $this->maxLocks) {
                throw new SemaphoreException('No locks acquired to release.');
            }

            ++$this->locks;
        });

        if (!$this->processQueue->isEmpty()) {
            $pid = $this->processQueue->dequeue();

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

        $this->synchronized(function () use (&$dequeue) {
            if ($this->locks > 0 && !$this->waitQueue->isEmpty()) {
                --$this->locks;
                $dequeue = true;
            }
        });

        if ($dequeue) {
            $this->waitQueue->dequeue()->resolve();
        }
    }

    public function destroy()
    {
        //$this->semaphore->destroy();
    }
}
