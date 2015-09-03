<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\SemaphoreException;
use Icicle\Coroutine;

/**
 * A non-blocking, interprocess POSIX semaphore.
 *
 * Uses a POSIX message queue to store a queue of permits in a lock-free data structure. This semaphore implementation
 * is preferred over other implementations when available, as it provides the best performance.
 *
 * Not compatible with Windows.
 */
class PosixSemaphore implements SemaphoreInterface, \Serializable
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var int The semaphore key.
     */
    private $key;

    /**
     * @var int The number of total locks.
     */
    private $maxLocks;

    /**
     * @var resource A message queue of available locks.
     */
    private $queue;

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $maxLocks    The maximum number of locks that can be acquired from the semaphore.
     * @param int $permissions Permissions to access the semaphore.
     *
     * @throws SemaphoreException If the semaphore could not be created due to an internal error.
     */
    public function __construct($maxLocks, $permissions = 0600)
    {
        $this->init($maxLocks, $permissions);
    }

    /**
     * @param int $maxLocks    The maximum number of locks that can be acquired from the semaphore.
     * @param int $permissions Permissions to access the semaphore.
     *
     * @throws SemaphoreException If the semaphore could not be created due to an internal error.
     */
    private function init($maxLocks, $permissions)
    {
        $maxLocks = (int) $maxLocks;
        if ($maxLocks < 1) {
            $maxLocks = 1;
        }

        $this->key = abs(crc32(spl_object_hash($this)));
        $this->maxLocks = $maxLocks;

        $this->queue = msg_get_queue($this->key, $permissions);
        if (!$this->queue) {
            throw new SemaphoreException('Failed to create the semaphore.');
        }

        // Fill the semaphore with locks.
        while (--$maxLocks >= 0) {
            $this->release();
        }
    }

    /**
     * Checks if the semaphore has been freed.
     *
     * @return bool True if the semaphore has been freed, otherwise false.
     */
    public function isFreed()
    {
        return !is_resource($this->queue) || !msg_queue_exists($this->key);
    }

    /**
     * Gets the maximum number of locks held by the semaphore.
     *
     * @return int The maximum number of locks held by the semaphore.
     */
    public function getSize()
    {
        return $this->maxLocks;
    }

    /**
     * Gets the access permissions of the semaphore.
     *
     * @return int A permissions mode.
     */
    public function getPermissions()
    {
        $stat = msg_stat_queue($this->queue);
        return $stat['msg_perm.mode'];
    }

    /**
     * Sets the access permissions of the semaphore.
     *
     * The current user must have access to the semaphore in order to change the permissions.
     *
     * @param int $mode A permissions mode to set.
     *
     * @throws SemaphoreException If the operation failed.
     */
    public function setPermissions($mode)
    {
        if (!msg_set_queue($this->queue, [
            'msg_perm.mode' => $mode
        ])) {
            throw new SemaphoreException('Failed to change the semaphore permissions.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $stat = msg_stat_queue($this->queue);
        return $stat['msg_qnum'];
    }

    /**
     * {@inheritdoc}
     */
    public function acquire()
    {
        while (true) {
            // Attempt to acquire a lock from the semaphore.
            if (@msg_receive($this->queue, 0, $type, 1, $chr, false, MSG_IPC_NOWAIT, $errno)) {
                // A free lock was found, so resolve with a lock object that can
                // be used to release the lock.
                yield new Lock(function (Lock $lock) {
                    $this->release();
                });
                return;
            }

            // Check for unusual errors.
            if ($errno !== MSG_ENOMSG) {
                throw new SemaphoreException('Failed to acquire a lock.');
            }

            // Sleep for a while, giving a chance for other threads to release
            // their locks. After we finish sleeping, we can check again to see
            // if it is our turn to acquire a lock.
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
        }
    }

    /**
     * Removes the semaphore if it still exists.
     *
     * @throws SemaphoreException If the operation failed.
     */
    public function free()
    {
        if (is_resource($this->queue) && msg_queue_exists($this->key)) {
            if (!msg_remove_queue($this->queue)) {
                throw new SemaphoreException('Failed to free the semaphore.');
            }

            $this->queue = null;
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
        // Get the semaphore key and attempt to re-connect to the semaphore in memory.
        list($this->key, $this->maxLocks) = unserialize($serialized);

        if (msg_queue_exists($this->key)) {
            $this->queue = msg_get_queue($this->key);
        }
    }

    /**
     * Clones the semaphore, creating a new semaphore with the same size and permissions.
     */
    public function __clone()
    {
        $this->init($this->maxLocks, $this->getPermissions());
    }

    /**
     * Releases a lock from the semaphore.
     *
     * @throws SemaphoreException If the operation failed.
     */
    protected function release()
    {
        // Call send in non-blocking mode. If the call fails because the queue
        // is full, then the number of locks configured is too large.
        if (!@msg_send($this->queue, 1, "\0", false, false, $errno)) {
            if ($errno === MSG_EAGAIN) {
                throw new SemaphoreException('The semaphore size is larger than the system allows.');
            }

            throw new SemaphoreException('Failed to release the lock.');
        }
    }
}
