<?php
namespace Icicle\Concurrent;

/**
 * An object that can be synchronized for exclusive access across contexts.
 */
interface SynchronizableInterface
{
    /**
     * @coroutine
     *
     * Invokes a function while maintaining a lock on the object.
     *
     * The given callback will be passed the object being synchronized on as the first argument.
     *
     * @param callable<self> $callback The synchronized function to invoke.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of $callback.
     */
    public function synchronized(callable $callback);
}
