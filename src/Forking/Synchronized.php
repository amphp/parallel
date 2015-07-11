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
    private $__key;
    private $__shm;
    private $__synchronizedProperties = [];
    protected $semaphore;

    /**
     * Creates a new synchronized object.
     */
    public function __construct()
    {
        $this->__key = abs(crc32(spl_object_hash($this)));
        $this->__open($this->__key, 'c', 0600, 1024);
        $this->__write(0, pack('x5'));
        $this->__initSynchronizedProperties();
        $this->semaphore = new Semaphore();
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
        $this->__writeSynchronizedProperties();
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
     * Destroys the synchronized object.
     */
    protected function destroy()
    {
        if (!shmop_delete($this->__shm)) {
            throw new SynchronizedMemoryException('Failed to discard shared memory block.');
        }
    }

    /**
     * Destroys the synchronized object safely on destruction.
     */
    public function __destruct()
    {
        $this->synchronized(function () {
            $this->destroy();
        });
    }

    /**
     * @internal
     */
    public function __isset($name)
    {
        $this->__readSynchronizedProperties();
        return isset($this->__synchronizedProperties[$name]);
    }

    /**
     * @internal
     */
    public function __get($name)
    {
        $this->__readSynchronizedProperties();
        return $this->__synchronizedProperties[$name];
    }

    /**
     * @internal
     */
    public function __set($name, $value)
    {
        $this->__readSynchronizedProperties();
        $this->__synchronizedProperties[$name] = $value;
        $this->__writeSynchronizedProperties();
    }

    /**
     * @internal
     */
    public function __unset($name)
    {
        $this->__readSynchronizedProperties();
        if (isset($this->__synchronizedProperties[$name])) {
            unset($this->__synchronizedProperties[$name]);
            $this->__writeSynchronizedProperties();
        }
    }

    /**
     * Initializes the internal synchronized property table.
     *
     * This method does some ugly hackery to put on a nice face elsewhere. At
     * call-time, the descendant type's defined and inherited properties are
     * scanned for \@synchronized annotations.
     *
     * @internal
     */
    private function __initSynchronizedProperties()
    {
        $class = new \ReflectionClass(get_called_class());
        $synchronizedProperties = [];

        do {
            foreach ($class->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $comment = $property->getDocComment();
                    if ($comment && strpos($comment, '@synchronized') !== false) {
                        $synchronizedProperties[$property->getName()] = $class->getName();
                    }
                }
            }
        } while ($class = $class->getParentClass());

        $unsetter = function ($name) {
            $initValue = $this->{$name};
            unset($this->{$name});
            return $initValue;
        };

        foreach ($synchronizedProperties as $property => $class) {
            $this->__synchronizedProperties[$property] = $unsetter
                ->bindTo($this, $class)
                ->__invoke($property);
        }
    }

    /**
     * @internal
     */
    private function __readSynchronizedProperties()
    {
        $data = $this->__read(0, 5);
        $header = unpack('Cstate/Lsize', $data);

        // State set to 1 indicates the memory is stale and has been moved to a
        // new location. Move handle and try to read again.
        if ($header['state'] === 1) {
            shmop_close($this->__shm);
            $this->__key = $header['size'];
            $this->__open($this->__key, 'w', 0, 0);
            $this->__readSynchronizedProperties();
            return;
        }

        if ($header['size'] > 0) {
            $data = $this->__read(5, $header['size']);
            $this->__synchronizedProperties = unserialize($data);
        }
    }

    /**
     * @internal
     */
    private function __writeSynchronizedProperties()
    {
        $serialized = serialize($this->__synchronizedProperties);
        $size = strlen($serialized);

        // If we run out of space, we need to allocate a new shared memory
        // segment that is larger than the current one. To coordinate with other
        // processes, we will leave a message in the old segment that the segment
        // has moved and along with the new key. The old segment will be discarded
        // automatically after all other processes notice the change and close
        // the old handle.
        if (shmop_size($this->__shm) < $size + 5) {
            $this->__key = $this->__key < 0xffffffff ? $this->__key + 1 : rand(0x10, 0xfffffffe);
            $header = pack('CL', 1, $this->__key);
            $this->__write(0, $header);
            $this->destroy();
            shmop_close($this->__shm);

            $this->__open($this->__key, 'c', 0600, $size * 2);
        }

        $data = pack('xLa*', $size, $serialized);
        $this->__write(0, $data);
    }

    /**
     * @internal
     */
    private function __open($key, $mode, $permissions, $size)
    {
        $this->__shm = shmop_open($key, $mode, $permissions, $size);
        if ($this->__shm === false) {
            throw new SynchronizedMemoryException('Failed to create shared memory block.');
        }
    }

    /**
     * @internal
     */
    private function __read($offset, $size)
    {
        $data = shmop_read($this->__shm, $offset, $size);
        if ($data === false) {
            throw new SynchronizedMemoryException('Failed to read from shared memory block.');
        }
        return $data;
    }

    /**
     * @internal
     */
    private function __write($offset, $data)
    {
        if (!shmop_write($this->__shm, $data, $offset)) {
            throw new SynchronizedMemoryException('Failed to write to shared memory block.');
        }
    }
}
