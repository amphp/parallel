<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\LocalObjectError;

/**
 * A storage container that stores an object in non-threaded memory.
 *
 * The storage container keeps the wrapped object in memory that is local for
 * the current thread. The object stored can be of any type, and can even be (or
 * rather, especially) non-thread-safe or non-serializable objects.
 *
 * This is useful for storing references to non-thread-safe objects from within
 * a thread. Normally, only thread-safe or serializable objects are allowed to
 * be stored as member variables. You can wrap such objects in a `LocalObject`
 * to store a reference to it safely.
 *
 * To access the wrapped object, you must call `LocalObject::deref()` to fetch a
 * reference to the object. If you think of a `LocalObject` as a fancy pointer
 * instead of an actual object, you will be less likely to forget to call
 * `deref()` before using the object.
 *
 * Note that the wrapped object will become static, and will not be implicitly
 * destroyed by the garbage collector. To destroy the object, you must call
 * `LocalObject::free()` for the object to be destroyed.
 */
class LocalObject implements \Serializable
{
    /**
     * @var int The object's local object ID.
     */
    private $objectId;

    /**
     * @var int The ID of the thread the object belongs to.
     */
    private $threadId;

    /**
     * @var int The next available object ID.
     */
    private static $nextId = 0;

    /**
     * Creates a new local object container.
     *
     * The object given will be assigned a new object ID and will have a
     * reference to it stored in memory local to the thread.
     *
     * @param object $object The object to store in the container.
     */
    public function __construct($object)
    {
        if (!is_object($object)) {
            throw new InvalidArgumentError('Value is not an object.');
        }

        // We can't use this object's hash as the ID because it may change as
        // the handle is passed around and serialized and unserialized.
        do {
            $this->objectId = self::$nextId++;
        } while (!$this->isFreed());
        $this->threadId = \Thread::getCurrentThreadId();

        // Store the object in the thread-local array.
        $this->getStorageContainer()->offsetSet($this->objectId, $object);
    }

    /**
     * Gets the object stored in the container.
     *
     * @return object The stored object.
     */
    public function deref()
    {
        if ($this->isFreed()) {
            throw new LocalObjectError('The object has already been freed.',
                $this->objectId,
                $this->threadId);
        }

        return $this->getStorageContainer()[$this->objectId];
    }

    /**
     * Releases the reference to the local object.
     *
     * If there are no other references to the object outside of this handle, it
     * will be destroyed by the garbage collector.
     */
    public function free()
    {
        unset($this->getStorageContainer()[$this->objectId]);
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
        return !isset($this->getStorageContainer()[$this->objectId]);
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
        return serialize([$this->objectId, $this->threadId]);
    }

    /**
     * Unserializes the local object handle.
     *
     * @param string $serialized The serialized object handle.
     */
    public function unserialize($serialized)
    {
        list($this->objectId, $this->threadId) = unserialize($serialized);

        if ($this->threadId !== \Thread::getCurrentThreadId()) {
            throw new LocalObjectError('Local objects cannot be passed to other threads.',
                $this->objectId,
                $this->threadId);
        }
    }

    /**
     * Handles cloning, which creates clones the local object and creates a new
     * local object handle.
     */
    public function __clone()
    {
        $object = clone $this->deref();
        $this->__construct($object);
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
                'id' => $this->objectId,
                'object' => null,
                'freed' => true,
            ];
        }

        return [
            'id' => $this->objectId,
            'object' => $this->deref(),
            'freed' => false,
        ];
    }

    /**
     * Fetches a global array of objects in non-threaded memory.
     *
     * @return \ArrayObject The non-threaded array.
     */
    private function getStorageContainer()
    {
        static $container;

        if ($container === null) {
            $container = new \ArrayObject();
        }

        return $container;
    }
}
