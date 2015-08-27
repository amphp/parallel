<?php
namespace Icicle\Concurrent;

/**
 * Interface for objects that can be synchronized across contexts.
 */
interface SynchronizableInterface
{
    /**
     * Invokes a function while maintaining a lock on the object.
     *
     * @param callable $callback The function to invoke.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function synchronized(callable $callback);
}
