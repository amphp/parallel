<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\SemaphoreException;

/**
 * A synchronous semaphore that uses System V IPC semaphores.
 */
class Semaphore implements SemaphoreInterface, \Serializable
{
    /**
     * @var int The key to the semaphore.
     */
    private $key;

    /**
     * @var int The maximum number of locks.
     */
    private $maxLocks;

    /**
     * @var resource An open handle to the semaphore.
     */
    private $handle;

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $maxLocks    The maximum number of locks that can be acquired from the semaphore.
     * @param int $permissions Permissions to access the semaphore.
     */
    public function __construct($maxLocks = 1, $permissions = 0600)
    {
        $this->key = abs(crc32(spl_object_hash($this)));
        $this->maxLocks = $maxLocks;
        $this->handle = sem_get($this->key, $maxLocks, $permissions, 1);

        if ($this->handle === false) {
            throw new SemaphoreException('Failed to create the semaphore.');
        }
    }

    /**
     * Gets the maximum number of locks.
     *
     * @return int The maximum number of locks.
     */
    public function getMaxLocks()
    {
        return $this->maxLocks;
    }

    /**
     * Acquires a lock from the semaphore.
     *
     * Blocks until a lock can be acquired.
     */
    public function acquire()
    {
        if (!sem_acquire($this->handle)) {
            throw new SemaphoreException('Failed to lock the semaphore.');
        }
    }

    /**
     * Releases a lock to the semaphore.
     */
    public function release()
    {
        if (!sem_release($this->handle)) {
            throw new SemaphoreException('Failed to unlock the semaphore.');
        }
    }

    /**
     * Removes the semaphore if it still exists.
     */
    public function destroy()
    {
        if (!@sem_remove($this->handle)) {
            $error = error_get_last();

            if ($error['type'] !== E_WARNING) {
                throw new SemaphoreException('Failed to remove the semaphore.');
            }
        }
    }

    /**
     * Serializes the semaphore.
     *
     * @return string The serialized semaphore.
     */
    public function serialize()
    {
        return serialize([$this->key, $this->maxLocks]);
    }

    /**
     * Unserializes a serialized semaphore.
     *
     * @param string $serialized The serialized semaphore.
     */
    public function unserialize($serialized)
    {
        // Get the semaphore key and attempt to re-connect to the semaphore in
        // memory.
        list($this->key, $this->maxLocks) = unserialize($serialized);
        $this->handle = sem_get($this->key, $maxLocks, 0600, 1);
    }
}
