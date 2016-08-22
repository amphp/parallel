<?php declare(strict_types = 1);

namespace Amp\Concurrent\Sync;

use Amp\Concurrent\LockAlreadyReleasedError;

/**
 * A handle on an acquired lock from a synchronization object.
 *
 * This object is not thread-safe; after acquiring a lock from a mutex or
 * semaphore, the lock should reside in the same thread or process until it is
 * released.
 */
class Lock {
    /**
     * @var callable The function to be called on release.
     */
    private $releaser;

    /**
     * @var bool Indicates if the lock has been released.
     */
    private $released = false;

    /**
     * Creates a new lock permit object.
     *
     * @param callable<Lock> $releaser A function to be called upon release.
     */
    public function __construct(callable $releaser) {
        $this->releaser = $releaser;
    }

    /**
     * Checks if the lock has already been released.
     *
     * @return bool True if the lock has already been released, otherwise false.
     */
    public function isReleased(): bool {
        return $this->released;
    }

    /**
     * Releases the lock.
     *
     * @throws LockAlreadyReleasedError If the lock was already released.
     */
    public function release() {
        if ($this->released) {
            throw new LockAlreadyReleasedError('The lock has already been released!');
        }

        // Invoke the releaser function given to us by the synchronization source
        // to release the lock.
        ($this->releaser)($this);
        $this->released = true;
    }

    /**
     * Releases the lock when there are no more references to it.
     */
    public function __destruct() {
        if (!$this->released) {
            $this->release();
        }
    }
}
