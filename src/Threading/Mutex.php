<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Sync\MutexInterface;

/**
 * A thread-safe mutex using the pthreads locking mechanism.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class Mutex extends \Threaded implements MutexInterface
{
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
        $this->handle = \Mutex::create($locked);
    }

    /**
     * {@inheritdoc}
     */
    public function lock()
    {
        \Mutex::lock($this->handle);
    }

    /**
     * {@inheritdoc}
     */
    public function unlock()
    {
        \Mutex::unlock($this->handle);
    }

    /**
     * Destroys the mutex.
     */
    public function __destruct()
    {
        \Mutex::destroy($this->handle);
    }
}
