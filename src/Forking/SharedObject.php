<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\Exception\SynchronizedMemoryException;

/**
 * A synchronized object that safely shares its state across processes and
 * provides methods for process synchronization.
 *
 * When used with forking, the object must be created prior to forking for both
 * processes to access the synchronized object.
 */
abstract class SharedObject
{
    /**
     * @var int The default amount of bytes to allocate for the object.
     */
    const SHM_DEFAULT_SIZE = 16384;

    /**
     * @var int The byte offset to the start of the object data in memory.
     */
    const SHM_DATA_OFFSET = 5;

    /**
     * @var int The default permissions for other processes to access the object.
     */
    const OBJECT_PERMISSIONS = 0600;

    /**
     * @var The shared memory segment key.
     */
    private $__key;

    /**
     * @var An open handle to the shared memory segment.
     */
    private $__shm;

    /**
     * @var array A local cache of property values that are synchronized.
     */
    private $__synchronizedProperties = [];

    /**
     * Creates a new synchronized object.
     */
    public function __construct()
    {
        $this->__key = abs(crc32(spl_object_hash($this)));
        $this->__open($this->__key, 'c', static::OBJECT_PERMISSIONS, static::SHM_DEFAULT_SIZE);
        $this->__write(0, pack('x5'));
        $this->__initSynchronizedProperties();
    }

    /**
     * Destroys the synchronized object.
     */
    public function destroy()
    {
        if (!shmop_delete($this->__shm)) {
            throw new SynchronizedMemoryException('Failed to discard shared memory block.');
        }
    }

    /**
     * Checks if a synchronized property is set.
     *
     * @param string $name The name of the property to check.
     *
     * @return bool True if the property is set, otherwise false.
     *
     * @internal
     */
    final public function __isset($name)
    {
        $this->__readSynchronizedProperties();
        return isset($this->__synchronizedProperties[$name]);
    }

    /**
     * Gets the value of a synchronized property.
     *
     * @param string $name The name of the property to get.
     *
     * @return mixed The value of the property.
     *
     * @internal
     */
    final public function __get($name)
    {
        $this->__readSynchronizedProperties();
        return $this->__synchronizedProperties[$name];
    }

    /**
     * Sets the value of a synchronized property.
     *
     * @param string $name  The name of the property to set.
     * @param mixed  $value The value to set the property to.
     *
     * @internal
     */
    final public function __set($name, $value)
    {
        $this->__readSynchronizedProperties();
        $this->__synchronizedProperties[$name] = $value;
        $this->__writeSynchronizedProperties();
    }

    /**
     * Unsets a synchronized property.
     *
     * @param string $name The name of the property to unset.
     *
     * @internal
     */
    final public function __unset($name)
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

        // Find *all* defined and inherited properties of the called class (late
        // binding) and get which class the property was defined in. This
        // includes inherited private properties.
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

        // Define a closure that deletes a property and returns its default
        // value. This function will be called on the current object to delete
        // synchronized properties (by being bound to the class scope that
        // defined the property).
        $unsetter = function ($name) {
            $initValue = $this->{$name};
            unset($this->{$name});
            return $initValue;
        };

        // Cache the synchronized property table.
        foreach ($synchronizedProperties as $property => $class) {
            $this->__synchronizedProperties[$property] = $unsetter
                ->bindTo($this, $class)
                ->__invoke($property);
        }
    }

    /**
     * Reloads the object's property table from shared memory.
     *
     * @internal
     */
    private function __readSynchronizedProperties()
    {
        $data = $this->__read(0, static::SHM_DATA_OFFSET);
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
            $data = $this->__read(static::SHM_DATA_OFFSET, $header['size']);
            $this->__synchronizedProperties = unserialize($data);
        }
    }

    /**
     * Writes the object's property table to shared memory.
     *
     * @internal
     */
    protected function __writeSynchronizedProperties()
    {
        $serialized = serialize($this->__synchronizedProperties);
        $size = strlen($serialized);

        // If we run out of space, we need to allocate a new shared memory
        // segment that is larger than the current one. To coordinate with other
        // processes, we will leave a message in the old segment that the segment
        // has moved and along with the new key. The old segment will be discarded
        // automatically after all other processes notice the change and close
        // the old handle.
        if (shmop_size($this->__shm) < $size + static::SHM_DATA_OFFSET) {
            $this->__key = $this->__key < 0xffffffff ? $this->__key + 1 : rand(0x10, 0xfffffffe);
            $header = pack('CL', 1, $this->__key);
            $this->__write(0, $header);
            $this->destroy();
            shmop_close($this->__shm);

            $this->__open($this->__key, 'c', static::OBJECT_PERMISSIONS, $size * 2);
        }

        $data = pack('xLa*', $size, $serialized);
        $this->__write(0, $data);
    }

    /**
     * Opens a shared memory handle.
     *
     * @param int    $key         The shared memory key.
     * @param string $mode        The mode to open the shared memory in.
     * @param int    $permissions Process permissions on the shared memory.
     * @param int    $size        The size to crate the shared memory in bytes.
     *
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
     * Reads binary data from shared memory.
     *
     * @param int $offset The offset to read from.
     * @param int $size   The number of bytes to read.
     *
     * @return string The binary data at the given offset.
     *
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
     * Writes binary data to shared memory.
     *
     * @param int    $offset The offset to write to.
     * @param string $data   The binary data to write.
     *
     * @internal
     */
    private function __write($offset, $data)
    {
        if (!shmop_write($this->__shm, $data, $offset)) {
            throw new SynchronizedMemoryException('Failed to write to shared memory block.');
        }
    }
}
