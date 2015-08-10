<?php
namespace Icicle\Concurrent\Sync;

/**
 * A thread-safe, asynchronous mutex using the pthreads locking mechanism.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class ThreadedMutex implements MutexInterface
{
    /**
     * @var \Icicle\Concurrent\Sync\InternalThreadedMutex
     */
    private $mutex;

    /**
     * Creates a new threaded mutex.
     *
     * @param bool $locked Whether the mutex should start out locked.
     */
    public function __construct($locked = false)
    {
        $this->mutex = new InternalThreadedMutex();
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        return $this->mutex->acquire();
    }
}
