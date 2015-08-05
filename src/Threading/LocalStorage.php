<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Exception\InvalidArgumentError;

/**
 * A storage container that stores data in the local thread only.
 *
 * The storage container acts like a dictionary, and can store any type of value,
 * including arrays and non-serializable or non-thread-safe objects. Numbers and
 * strings are valid keys.
 */
class LocalStorage implements \Countable, \IteratorAggregate, \ArrayAccess
{
    /**
     * @var LocalObject A thread-local array object of items.
     */
    private $array;

    /**
     * Creates a new local storage container.
     */
    public function __construct()
    {
        $this->array = new LocalObject(new \ArrayObject());
    }

    /**
     * Counts the number of items in the local storage.
     *
     * @return int The number of items.
     */
    public function count()
    {
        return count($this->array->deref());
    }

    /**
     * Gets an iterator for iterating over all the items in the local storage.
     *
     * @return \Traversable A new iterator.
     */
    public function getIterator()
    {
        return $this->array->deref()->getIterator();
    }

    /**
     * Removes all items from the local storage.
     */
    public function clear()
    {
        $this->array->deref()->exchangeArray([]);
    }

    /**
     * Checks if a given item exists.
     *
     * @param int|string $key The key of the item to check.
     *
     * @return bool True if the item exists, otherwise false.
     */
    public function offsetExists($key)
    {
        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }

        return $this->array->deref()->offsetExists($key);
    }

    /**
     * Gets an item's value from the local storage.
     *
     * @param int|string $key The key of the item to get.
     *
     * @return mixed The item value.
     */
    public function offsetGet($key)
    {
        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }

        if (!$this->offsetExists($key)) {
            throw new InvalidArgumentError("The key '{$key}' does not exist.");
        }

        return $this->array->deref()->offsetGet($key);
    }

    /**
     * Sets the value of an item.
     *
     * @param int|string $key   The key of the item to set.
     * @param mixed      $value The value to set.
     */
    public function offsetSet($key, $value)
    {
        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }

        $this->array->deref()->offsetSet($key, $value);
    }

    /**
     * Removes an item from the local storage.
     *
     * @param int|string $key The key of the item to remove.
     */
    public function offsetUnset($key)
    {
        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }

        $this->array->deref()->offsetUnset($key);
    }

    /**
     * Frees the local storage and all items.
     */
    public function free()
    {
        $this->array->free();
    }

    /**
     * Gets a copy of all the items in the local storage.
     *
     * @return array An array of item keys and values.
     */
    public function __debugInfo()
    {
        return $this->array->deref()->getArrayCopy();
    }
}
