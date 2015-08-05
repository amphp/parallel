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
 * Note that the wrapped object will become static, and will not be implicitly
 * destroyed by the garbage collector. To destroy the object, you must call
 * `LocalObject::free()` for the object to be destroyed.
 */
class LocalObject implements \Serializable
{
    /**
     * @var string This object's local object ID.
     */
    private $objectId;

    /**
     * @var int The ID of the thread the object belongs to.
     */
    private $threadId;

    /**
     * Creates a new local object container.
     *
     * @param object The object to store in the container.
     */
    public function __construct($object)
    {
        if (!is_object($object)) {
            throw new InvalidArgumentError('Value is not an object.');
        }

        // We can't use this object's hash as the ID because it may change as
        // the handle is passed around and serialized and unserialized.
        $this->objectId = uniqid('LO#', true);
        $this->threadId = \Thread::getCurrentThreadId();

        // Store the object in the thread-local array.
        $this->getStorageContainer()->offsetSet($this->objectId, $object);
    }

    /**
     * Gets the object stored in the container.
     *
     * @return \stdClass The stored object.
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
     * If there are no other references to the object, it will be destroyed.
     */
    public function free()
    {
        unset($this->getStorageContainer()[$this->objectId]);
    }

    /**
     * Checks if the object has been freed.
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
