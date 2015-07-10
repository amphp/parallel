<?php
namespace Icicle\Concurrent;

/**
 * A synchronous semaphore that uses System V IPC semaphores.
 */
class Semaphore
{
    private $key;
    private $identifier;

    /**
     * Creates a new semaphore.
     *
     * @param int $max         The maximum number of processes that can lock the semaphore.
     * @param int $permissions Permissions to access the semaphore.
     */
    public function __construct($max = 1, $permissions = 0666)
    {
        $this->key = abs(crc32(spl_object_hash($this)));
        $this->identifier = sem_get($this->key, $max, $permissions);

        if ($this->identifier === false) {
            throw new SemaphoreException('Failed to create the semaphore.');
        }
    }

    public function __destruct()
    {
        $this->remove();
    }

    /**
     * Locks the semaphore.
     *
     * Blocks until the semaphore is locked.
     */
    public function lock()
    {
        if (!sem_acquire($this->identifier)) {
            throw new SemaphoreException('Failed to lock the semaphore.');
        }
    }

    /**
     * Unlocks the semaphore.
     */
    public function unlock()
    {
        if (!sem_release($this->identifier)) {
            throw new SemaphoreException('Failed to unlock the semaphore.');
        }
    }

    /**
     * Removes the semaphore if it still exists.
     */
    public function remove()
    {
        if (!@sem_remove($this->identifier)) {
            $error = error_get_last();

            if ($error['type'] !== E_WARNING) {
                throw new SemaphoreException('Failed to remove the semaphore.');
            }
        }
    }
}
