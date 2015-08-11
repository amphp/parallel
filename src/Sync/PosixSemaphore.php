<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\SemaphoreException;
use Icicle\Coroutine;

/**
 * An asynchronous semaphore that uses POSIX semaphores.
 *
 * Compatible only with POSIX-compliant systems that provide the System V IPC
 * methods.
 *
 * Requires PHP 5.6.1+.
 */
class PosixSemaphore implements SemaphoreInterface, \Serializable
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var int The semaphore key.
     */
    private $key;

    /**
     * @var resource An open handle to a gatekeeper semaphore.
     */
    private $semaphore;

    /**
     * @var SharedObject A shared object containing the lock count and queue.
     */
    private $data;

    /**
     * Creates a new semaphore.
     *
     * @param int $maxLocks The maximum number of processes that can lock the
     *                      semaphore.
     */
    public function __construct($maxLocks)
    {
        $this->key = abs(crc32(spl_object_hash($this)));

        $this->semaphore = sem_get($this->key, 1, 0600, 1);
        if (!$this->semaphore) {
            throw new SemaphoreException('Failed to create the semaphore.');
        }

        $this->data = new SharedObject([
            'locks' => (int)$maxLocks,
            'waitQueue' => [],
        ]);
    }

    /**
     * Acquires a lock from the semaphore.
     *
     * Blocks until a lock can be acquired.
     */
    public function acquire()
    {
        $this->lock();

        try {
            $data = $this->data->deref();

            // Attempt to acquire a lock from the semaphore. If no locks are
            // available immediately, we have a lot of work to do...
            if ($data['locks'] <= 0 || !empty($data['waitQueue'])) {
                // Since there are no free locks that we can claim yet, we need
                // to add our request to the queue of other threads waiting for
                // a free lock to make sure we don't steal one from another
                // thread that has been waiting longer than us.
                $id = mt_rand();
                $data['waitQueue'][] = $id;
                $this->data->set($data);

                // Sleep for a while, giving a chance for other threads to release
                // their locks. After we finish sleeping, we can check again to see
                // if it is our turn to acquire a lock.
                do {
                    $this->unlock();
                    yield Coroutine\sleep(self::LATENCY_TIMEOUT);
                    $this->lock();
                    $data = $this->data->deref();
                } while ($data['locks'] <= 0 || $data['waitQueue'][0] !== $id);
            }

            // At this point, we have made sure that one of the locks in the
            // semaphore is available for us to use, so decrement the lock count
            // to mark it as taken, and return a lock object that represents the
            // lock just acquired.
            $data = $this->data->deref();
            --$data['locks'];
            $data['waitQueue'] = array_slice($data['waitQueue'], 1);
            $this->data->set($data);

            yield new Lock(function (Lock $lock) {
                $this->release();
            });
        } finally {
            $this->unlock();
        }
    }

    /**
     * Removes the semaphore if it still exists.
     */
    public function destroy()
    {
        if (!@sem_remove($this->semaphore)) {
            $error = error_get_last();

            if ($error['type'] !== E_WARNING) {
                throw new SemaphoreException('Failed to remove the semaphore.');
            }
        }

        $this->data->free();
    }

    /**
     * Serializes the semaphore.
     *
     * @return string The serialized semaphore.
     */
    public function serialize()
    {
        return serialize([$this->key, $this->data]);
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
        list($this->key, $this->data) = unserialize($serialized);
        $this->semaphore = sem_get($this->key, 1, 0600, 1);
    }

    /**
     * Releases a lock from the semaphore.
     */
    protected function release()
    {
        $this->lock();

        $data = $this->data->deref();
        ++$data['locks'];
        $this->data->set($data);

        $this->unlock();
    }

    /**
     * Locks the gatekeeper semaphore.
     */
    private function lock()
    {
        if (!sem_acquire($this->semaphore)) {
            throw new SemaphoreException('Failed to lock the semaphore.');
        }
    }

    /**
     * Unlocks the gatekeeper semaphore.
     */
    private function unlock()
    {
        if (!sem_release($this->semaphore)) {
            throw new SemaphoreException('Failed to unlock the semaphore.');
        }
    }
}
