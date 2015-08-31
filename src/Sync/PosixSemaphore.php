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
        $this->key = abs(crc32(spl_object_hash($this)));

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

            unset($this->queue);
        }
    }

    /**
     * Serializes the semaphore.
     *
     * @return string The serialized semaphore.
     */
    public function serialize()
    {
        return serialize($this->key);
    }

    /**
     * Unserializes a serialized semaphore.
     *
     * @param string $serialized The serialized semaphore.
     */
    public function unserialize($serialized)
    {
        // Get the semaphore key and attempt to re-connect to the semaphore in memory.
        $this->key = unserialize($serialized);

        if (msg_queue_exists($this->key)) {
            $this->queue = msg_get_queue($this->key);
        }
    }

    /**
     * Releases a lock from the semaphore.
     *
     * @throws SemaphoreException If the operation failed.
     */
    protected function release()
    {
        // Call send in blocking mode, since it is impossible for the queue to
        // be more full than when it began.
        if (!@msg_send($this->queue, 1, "\0", false)) {
            throw new SemaphoreException('Failed to release the lock.');
        }
    }
}
