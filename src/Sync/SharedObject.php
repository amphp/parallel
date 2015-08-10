<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\SharedMemoryException;

/**
 * A container object for sharing a value across contexts.
 *
 * A shared object is a container that stores an object inside shared memory.
 * The object can be accessed and mutated by any thread or process. The shared
 * object handle itself is serializable and can be sent to any thread or process
 * to give access to the value that is shared in the container.
 *
 * Note that accessing a shared object is not atomic. Access to a shared object
 * should be protected with a mutex to preserve data integrity.
 */
class SharedObject implements \Serializable
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

    // A list of valid states the object can be in.
    const STATE_UNALLOCATED = 0;
    const STATE_ALLOCATED = 1;
    const STATE_MOVED = 2;
    const STATE_FREED = 3;

    /**
     * @var int The shared memory segment key.
     */
    private $key;

    /**
     * @var int An open handle to the shared memory segment.
     */
    private $handle;

    /**
     * Creates a new local object container.
     *
     * The object given will be assigned a new object ID and will have a
     * reference to it stored in memory local to the thread.
     *
     * @param mixed $value The value to store in the container.
     */
    public function __construct($value)
    {
        $this->key = abs(crc32(spl_object_hash($this)));
        $this->memOpen($this->key, 'n', static::OBJECT_PERMISSIONS, static::SHM_DEFAULT_SIZE);
        $this->set($value);
    }

    /**
     * Gets the shared value stored in the container.
     *
     * @return mixed The stored value.
     */
    public function deref()
    {
        if ($this->isFreed()) {
            throw new SharedMemoryException('The object has already been freed.');
        }

        // Read from the memory block and handle moved blocks until we find the
        // correct block.
        do {
            $data = $this->memGet(0, static::SHM_DATA_OFFSET);
            $header = unpack('Cstate/Lsize', $data);

            // If the state is STATE_MOVED, the memory is stale and has been moved
            // to a new location. Move handle and try to read again.
            if ($header['state'] !== static::STATE_MOVED) {
                break;
            }

            shmop_close($this->handle);
            $this->key = $header['size'];
            $this->memOpen($this->key, 'w', 0, 0);
        } while (true);

        // Make sure the header is in a valid state and format.
        if ($header['state'] !== static::STATE_ALLOCATED || $header['size'] <= 0) {
            throw new SharedMemoryException('Shared object memory is corrupt.');
        }

        // Read the actual value data from memory and unserialize it.
        $data = $this->memGet(static::SHM_DATA_OFFSET, $header['size']);
        return unserialize($data);
    }

    /**
     * Sets the value in the container to a new value.
     *
     * @param mixed $value The value to set.
     */
    public function set($value)
    {
        $serialized = serialize($value);
        $size = strlen($serialized);

        // If we run out of space, we need to allocate a new shared memory
        // segment that is larger than the current one. To coordinate with other
        // processes, we will leave a message in the old segment that the segment
        // has moved and along with the new key. The old segment will be discarded
        // automatically after all other processes notice the change and close
        // the old handle.
        if (shmop_size($this->handle) < $size + static::SHM_DATA_OFFSET) {
            $this->key = $this->key < 0xffffffff ? $this->key + 1 : mt_rand(0x10, 0xfffffffe);
            $header = pack('CL', static::STATE_MOVED, $this->key);
            $this->memSet(0, $header);
            $this->memDelete();
            shmop_close($this->handle);

            $this->memOpen($this->key, 'n', static::OBJECT_PERMISSIONS, $size * 2);
        }

        // Rewrite the header and the serialized value to memory.
        $data = pack('CLa*', static::STATE_ALLOCATED, $size, $serialized);
        $this->memSet(0, $data);
    }

    /**
     * Frees the shared object from memory.
     *
     * The memory containing the shared value will be invalidated. When all
     * process disconnect from the object, the shared memory block will be
     * destroyed.
     */
    public function free()
    {
        // Invalidate the memory block by setting its state to FREED.
        $this->memSet(0, pack('Cx4', static::STATE_FREED));

        // Request the block to be deleted, then close our local handle.
        $this->memDelete();
        shmop_close($this->handle);
    }

    /**
     * Checks if the object has been freed.
     *
     * Note that this does not check if the object has been destroyed; it only
     * checks if this handle has freed its reference to the object.
     *
     * @return bool True if the object is freed, otherwise false.
     */
    public function isFreed()
    {
        $handle = @shmop_open($this->key, 'a', 0, 0);

        // If we could connect to the memory block, check if it has been
        // invalidated.
        if ($handle !== false) {
            $data = $this->memGet(0, static::SHM_DATA_OFFSET);
            $header = unpack('Cstate/Lsize', $data);
            shmop_close($handle);
            return $header['state'] === static::STATE_FREED;
        }

        return true;
    }

    /**
     * Serializes the local object handle.
     *
     * Note that this does not serialize the object that is referenced, just the
     * object handle.
     *
     * @return string The serialized object handle.
     */
    public function serialize()
    {
        return serialize($this->key);
    }

    /**
     * Unserializes the local object handle.
     *
     * @param string $serialized The serialized object handle.
     */
    public function unserialize($serialized)
    {
        $this->key = unserialize($serialized);
    }

    /**
     * Handles cloning, which creates clones the local object and creates a new
     * local object handle.
     */
    public function __clone()
    {
        $value = $this->deref();
        $this->__construct($value);
    }

    /**
     * Gets information about the object for debugging purposes.
     *
     * @return array An array of debugging information.
     */
    public function __debugInfo()
    {
        if ($this->isFreed()) {
            return [
                'id' => $this->key,
                'object' => null,
                'freed' => true,
            ];
        }

        return [
            'id' => $this->key,
            'object' => $this->deref(),
            'freed' => false,
        ];
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
    private function memOpen($key, $mode, $permissions, $size)
    {
        $this->handle = @shmop_open($key, $mode, $permissions, $size);
        if ($this->handle === false) {
            throw new SharedMemoryException('Failed to create shared memory block.');
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
    private function memGet($offset, $size)
    {
        $data = shmop_read($this->handle, $offset, $size);
        if ($data === false) {
            throw new SharedMemoryException('Failed to read from shared memory block.');
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
    private function memSet($offset, $data)
    {
        if (!shmop_write($this->handle, $data, $offset)) {
            throw new SharedMemoryException('Failed to write to shared memory block.');
        }
    }

    /**
     * Requests the shared memory segment to be deleted.
     *
     * @internal
     */
    private function memDelete()
    {
        if (!shmop_delete($this->handle)) {
            throw new SharedMemoryException('Failed to discard shared memory block.');
        }
    }
}
