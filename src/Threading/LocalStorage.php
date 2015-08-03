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
     * @var string The key where this local storage's data is stored.
     */
    private $storageKey;

    /**
     * Creates a new local storage container.
     */
    public function __construct()
    {
        global $__localStorage;

        $this->storageKey = spl_object_hash($this);
        $__localStorage[$this->storageKey] = [];
    }

    /**
     * Counts the number of items in the local storage.
     *
     * @return int The number of items.
     */
    public function count()
    {
        global $__localStorage;
        return count($__localStorage[$this->storageKey]);
    }

    /**
     * Gets an iterator for iterating over all the items in the local storage.
     *
     * @return \Traversable A new iterator.
     */
    public function getIterator()
    {
        global $__localStorage;
        return new \ArrayIterator($__localStorage[$this->storageKey]);
    }

    /**
     * Removes all items from the local storage.
     */
    public function clear()
    {
        global $__localStorage;
        $__localStorage[$this->storageKey] = [];
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
        global $__localStorage;

        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }
        return isset($__localStorage[$this->storageKey][$key]);
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
        global $__localStorage;

        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }

        if (!isset($__localStorage[$this->storageKey][$key])) {
            throw new InvalidArgumentError("The key '{$key}' does not exist.");
        }

        return $__localStorage[$this->storageKey][$key];
    }

    /**
     * Sets the value of an item.
     *
     * @param int|string $key   The key of the item to set.
     * @param mixed      $value The value to set.
     */
    public function offsetSet($key, $value)
    {
        global $__localStorage;

        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }
        $__localStorage[$this->storageKey][$key] = $value;
    }

    /**
     * Removes an item from the local storage.
     *
     * @param int|string $key The key of the item to remove.
     */
    public function offsetUnset($key)
    {
        global $__localStorage;

        if (!is_int($key) && !is_string($key)) {
            throw new InvalidArgumentError('Key must be an integer or string.');
        }
        unset($__localStorage[$this->storageKey][$key]);
    }

    /**
     * Removes the local storage from the current thread's memory.
     */
    public function __destruct()
    {
        global $__localStorage;
        unset($__localStorage[$this->storageKey]);
    }
}
