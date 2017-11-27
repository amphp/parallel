<?php

namespace Amp\Parallel\Sync;

use Amp\Promise;

/**
 * A parcel object for sharing data across execution contexts.
 *
 * A parcel is an object that stores a value in a safe way that can be shared
 * between different threads or processes. Different handles to the same parcel
 * will access the same data, and a parcel handle itself is serializable and
 * can be transported to other execution contexts.
 *
 * Wrapping and unwrapping values in the parcel are not atomic. To prevent race
 * conditions and guarantee safety, you should use the provided synchronization
 * methods to acquire a lock for exclusive access to the parcel first before
 * accessing the contained value.
 */
interface Parcel {
    /**
     * Asynchronously invokes a callback while maintaining an exclusive lock on the parcel. The current value of the
     * parcel is provided as the first argument to the callback function.
     *
     * The arguments passed to the callback depend on the implementing object. If the callback throws an exception,
     * the lock on the object will be immediately released.
     *
     * @param callable $callback The synchronized callback to invoke.
     *     The callback may be a regular function or a coroutine.
     *
     * @return \Amp\Promise<mixed> Resolves with the return value of $callback or fails if $callback
     *     throws an exception.
     */
    public function synchronized(callable $callback): Promise;

    /**
     * Unwraps the parcel and returns the value inside the parcel.
     *
     * @return mixed The value inside the parcel.
     */
    public function unwrap(): Promise;

    /**
     * Clones the parcel object, resulting in a new, independent parcel.
     *
     * When a parcel is cloned, a new parcel is created and the original
     * parcel's value is duplicated and copied to the new parcel.
     */
    public function __clone();
}
