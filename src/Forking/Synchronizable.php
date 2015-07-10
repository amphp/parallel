<?php
namespace Icicle\Concurrent\Forking;

abstract class Synchronizable
{
    private $memoryBlock;
    private $memoryKey;

    public function __construct()
    {
        $this->memoryKey = abs(crc32(spl_object_hash($this)));
        $this->memoryBlock = shm_attach($this->memoryKey, 8192);
        if (!is_resource($this->memoryBlock)) {
            throw new \Exception();
        }
    }

    public function __isset($name)
    {
        $key = abs(crc32($name));
        return shm_has_var($this->memoryBlock, $key);
    }

    public function __get($name)
    {
        $key = abs(crc32($name));
        if (shm_has_var($this->memoryBlock, $key)) {
            $serialized = shm_get_var($this->memoryBlock, $key);
            return unserialize($serialized);
        }
    }

    public function __set($name, $value)
    {
        $key = abs(crc32($name));
        if (!shm_put_var($this->memoryBlock, $key, serialize($value))) {
            throw new \Exception();
        }
    }

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
