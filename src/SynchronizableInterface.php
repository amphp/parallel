<?php
namespace Icicle\Concurrent;

/**
 * Interface for objects that can be synchronized across contexts.
 */
interface SynchronizableInterface
{
    /**
     * @coroutine
     *
     * Invokes a function while maintaining a lock on the object.
     *
     * @param callable $callback The function to invoke.
     *
     * @return \Generator
     *
     * @resolve mixed Return value of $callback.
     */
    public function synchronized(callable $callback);
}
