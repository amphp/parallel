<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\Semaphore;

/**
 * A synchronized object that safely shares its state across processes and
 * provides methods for process synchronization.
 */
abstract class Synchronized
{
    private $memoryBlock;
    private $memoryKey;
    private $semaphore;

    /**
     * Creates a new synchronized object.
     */
    public function __construct()
    {
        $this->semaphore = new Semaphore();
        $this->memoryKey = abs(crc32(spl_object_hash($this)));
        $this->memoryBlock = shm_attach($this->memoryKey, 8192);
        if (!is_resource($this->memoryBlock)) {
            throw new \Exception();
        }
    }

    /**
     * Locks the object for read or write for the calling context.
     */
    public function lock()
    {
        $this->semaphore->lock();
    }

    /**
     * Unlocks the object.
     */
    public function unlock()
    {
        $this->semaphore->unlock();
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
        $returnValue = $callback($this);
        $this->unlock();
        return $returnValue;
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
            throw new \Exception();
        }
    }

    /**
     * @internal
     */
    public function __unset($name)
    {
        $key = abs(crc32($name));
        if (!shm_remove_var($this->memoryBlock, $key)) {
            throw new \Exception();
        }
    }

    public function __destruct()
    {
        if ($this->memoryBlock) {
            if (!shm_remove($this->memoryBlock)) {
                throw new \Exception();
            }
        }
    }
}
