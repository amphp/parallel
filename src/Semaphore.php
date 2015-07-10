<?php
namespace Icicle\Concurrent;

use Icicle\Concurrent\Exception\SemaphoreException;

/**
 * A synchronous semaphore that uses System V IPC semaphores.
 */
class Semaphore implements \Serializable
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
     * Creates a new semaphore.
     *
     * @param int $maxLocks    The maximum number of processes that can lock the semaphore.
     * @param int $permissions Permissions to access the semaphore.
     */
    public function __construct($maxLocks = 1, $permissions = 0666)
    {
        $this->key = abs(crc32(spl_object_hash($this)));
        $this->maxLocks = $maxLocks;
        $this->handle = sem_get($this->key, $maxLocks, $permissions, 0);

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
     * Locks the semaphore.
     *
     * Blocks until the semaphore is locked.
     */
    public function lock()
    {
        if (!sem_acquire($this->handle)) {
            throw new SemaphoreException('Failed to lock the semaphore.');
        }
    }

    /**
     * Unlocks the semaphore.
     */
    public function unlock()
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
        $this->handle = sem_get($this->key, $maxLocks);
    }
}
