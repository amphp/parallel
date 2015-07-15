<?php
namespace Icicle\Concurrent;

/**
 * Interface for objects that can be synchronized across contexts.
 */
interface SynchronizableInterface
{
    /**
     * Acquires a lock on the context.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function lock();

    /**
     * Unlocks the context.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function unlock();

    /**
     * Invokes a function while maintaining a lock for the calling context.
     *
     * @param callable $callback The function to invoke.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function synchronized(callable $callback);
}
