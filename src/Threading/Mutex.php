<?php
namespace Icicle\Concurrent\Threading;

/**
 * A thread-safe mutex.
 *
 * Operations are guaranteed to be atomic.
 *
 * Compatible with POSIX systems and Microsoft Windows.
 */
class Mutex extends \Threaded
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
     * Locks the mutex.
     */
    public function lock()
    {
        \Mutex::lock($this->handle);
    }

    /**
     * Unlocks the mutex.
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
