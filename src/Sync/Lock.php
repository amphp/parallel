<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\LockAlreadyReleasedError;

/**
 * A handle on an acquired lock from a synchronization object.
 *
 * This object is not thread-safe; after acquiring a lock from a mutex or
 * semaphore, the lock should reside in the same thread or process until it is
 * released.
 */
class Lock
{
    /**
     * @var callable The function to be called on release.
     */
    private $releaser;

    /**
     * @var bool Indicates if the lock has been released.
     */
    private $released;

    /**
     * Creates a new lock permit object.
     *
     * @param callable<Lock> $releaser A function to be called upon release.
     */
    public function __construct(callable $releaser)
    {
        $this->releaser = $releaser;
    }

    /**
     * Checks if the lock has already been released.
     *
     * @return bool True if the lock has already been released, otherwise false.
     */
    public function isReleased()
    {
        return $this->released;
    }

    /**
     * Releases the lock.
     *
     * @throws LockAlreadyReleasedError Thrown if the lock was already released.
     */
    public function release()
    {
        if ($this->released) {
            throw new LockAlreadyReleasedError();
        }

        // Invoke the releaser function given to us by the synchronization source
        // to release the lock.
        call_user_func($this->releaser, $this);
        $this->released = true;
    }

    /**
     * Releases the lock when there are no more references to it.
     */
    public function __destruct()
    {
        if (!$this->released) {
            $this->release();
        }
    }
}
