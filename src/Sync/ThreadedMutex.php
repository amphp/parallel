<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\MutexException;
use Icicle\Coroutine;
use Mutex;

/**
 * A thread-safe, asynchronous mutex using the pthreads locking mechanism.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class ThreadedMutex implements MutexInterface
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var long A unique handle ID on a system mutex.
     */
    private $handle;

    /**
     * Creates a new threaded mutex.
     *
     * @param bool $locked Whether the mutex should start out locked.
     */
    public function __construct($locked = false)
    {
        $this->handle = Mutex::create($locked);
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        // Try to access the lock. If we can't get the lock, set an asynchronous
        // timer and try again.
        while (!Mutex::trylock($this->handle)) {
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
        }

        // Return a lock object that can be used to release the lock on the mutex.
        yield new Lock(function (Lock $lock) {
            $this->release();
        });
    }

    /**
     * Destroys the mutex.
     *
     * @throws MutexException Thrown if the operation fails.
     */
    public function destroy()
    {
        if (!Mutex::destroy($this->handle)) {
            throw new MutexException('Failed to destroy the mutex. Did you forget to unlock it first?');
        }
    }

    /**
     * Releases the lock from the mutex.
     *
     * @throws MutexException Thrown if the operation fails.
     */
    protected function release()
    {
        if (!Mutex::unlock($this->handle)) {
            throw new MutexException('Failed to unlock the mutex.');
        }
    }
}
