---
title: Custom tasks
permalink: /custom-tasks
---
Instead of passing simple callables to workers, this package also allows custom implementations of the `Task` interface to dispatch work in child processes or threads.

## `Task`

The `Task` interface has a single `run()` method that gets invoked in the worker to dispatch the work that needs to be done.

```php
<?php

namespace Amp\Parallel\Worker;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     * @param Environment
     *
     * @return mixed|\Amp\Promise|\Generator
     */
    public function run(Environment $environment);
}
```

Task instances are `serialize`'d in the main process and `unserialize`'d in the worker.
That means that all data that is passed between the main process and a worker needs to be serializable.

## `Environment`

The passed `Environment` allows to persist data between multiple tasks executed by the same worker, e.g. database connections or file handles, without resorting to globals for that.
Additionally `Environment` allows setting a TTL for entries, so can be used as a cache.

```php
<?php

namespace Amp\Parallel\Worker;

interface Environment extends \ArrayAccess
{
    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key): bool;

    /**
     * @param string $key
     *
     * @return mixed|null Returns null if the key does not exist.
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param mixed $value Using null for the value deletes the key.
     * @param int $ttl Number of seconds until data is automatically deleted. Use 0 for unlimited TTL.
     */
    public function set(string $key, $value, int $ttl = null);

    /**
     * @param string $key
     */
    public function delete(string $key);

    /**
     * Removes all values.
     */
    public function clear();
}
```