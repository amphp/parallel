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
     * Asynchronously invokes a callback while maintaining an exclusive lock on the object.
     *
     * The given callback will be passed the object being synchronized on as the first argument. If the callback throws
     * an exception, the lock on the object will be immediately released.
     *
     * @param callable<(mixed $data): mixed> $callback The synchronized callback to invoke.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of $callback.
     */
    public function synchronized(callable $callback);
}
