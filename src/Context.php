<?php
namespace Icicle\Concurrent;

/**
 * Interface for all types of execution contexts.
 */
interface Context
{
    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning();

    /**
     * Acquires a lock on the context asynchronously.
     *
     * @return PromiseInterface Promise that is resolved when the lock is acquired.
     */
    public function lock();

    /**
     * Unlocks the context.
     *
     * @return bool True if successful, otherwise false.
     */
    public function unlock();

    /**
     * Executes a callback with write access to the context data.
     *
     * The callback is executed asynchronously in the future when other contexts
     * are no longer synchronizing.
     *
     * @return PromiseInterface Promise that is resolved when the synchronization completes.
     */
    public function synchronize(callable $callback);

    /**
     * Starts the context execution.
     */
    public function start();

    /**
     * Blocks the caller's execution until the referenced context finishes.
     */
    public function join();

    /**
     * Executes the context's main code.
     */
    public function run();
}
