<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\Semaphore;
use Icicle\Concurrent\Exception\SynchronizedMemoryException;

/**
 * A synchronized object that safely shares its state across processes and
 * provides methods for process synchronization.
 *
 * When used with forking, the object must be created prior to forking for both
 * processes to access the synchronized object.
 */
abstract class Synchronized
{
    private $memoryBlock;
    private $memoryKey;
    protected $semaphore;

    /**
     * Creates a new synchronized object.
     */
    public function __construct()
    {
        $this->semaphore = new Semaphore();
        $this->memoryKey = abs(crc32(spl_object_hash($this)));
        $this->memoryBlock = shm_attach($this->memoryKey, 8192);
        if (!is_resource($this->memoryBlock)) {
            throw new SynchronizedMemoryException('Failed to create shared memory block.');
        }
    }

    /**
     * Locks the object for read or write for the calling context.
     */
    public function lock()
    {
        $this->semaphore->acquire();
    }

    /**
     * Unlocks the object.
     */
    public function unlock()
    {
        $this->semaphore->release();
    }

    /**
     * Invokes a function while maintaining a lock for the calling context.
     *
     * @param callable $callback The function to invoke.
     *
     * @return mixed The value returned by the callback.
     */
    public function synchronized(callable $callback)
    {
        $this->lock();

        try {
            $returnValue = $callback($this);
        } finally {
            $this->unlock();
        }

        return $returnValue;
    }

    /**
     * Destroys the synchronized object safely.
     */
    public function __destruct()
    {
        if (is_resource($this->memoryBlock)) {
            $this->synchronized(function () {
                if (!shm_remove($this->memoryBlock)) {
                    throw new SynchronizedMemoryException('Failed to discard shared memory block.');
                }
            });
        }
    }

    /**
     * @internal
     */
    public function __isset($name)
    {
        $key = abs(crc32($name));
        return shm_has_var($this->memoryBlock, $key);
    }

    /**
     * @internal
     */
    public function __get($name)
    {
        $key = abs(crc32($name));
        if (shm_has_var($this->memoryBlock, $key)) {
            $serialized = shm_get_var($this->memoryBlock, $key);

            if ($serialized === false) {
                throw new SynchronizedMemoryException('Failed to read from shared memory block.');
            }

            return unserialize($serialized);
        }
    }

    /**
     * @internal
     */
    public function __set($name, $value)
    {
        $key = abs(crc32($name));
        if (!shm_put_var($this->memoryBlock, $key, serialize($value))) {
            throw new SynchronizedMemoryException('Failed to write to shared memory block.');
        }
    }

    /**
     * @internal
     */
    public function __unset($name)
    {
        $key = abs(crc32($name));
        if (!shm_remove_var($this->memoryBlock, $key)) {
            throw new SynchronizedMemoryException('Failed to erase data in shared memory block.');
        }
    }
}
