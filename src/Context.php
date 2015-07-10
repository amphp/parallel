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
     * Acquires a lock on the context.
     */
    public function lock();

    /**
     * Unlocks the context.
     */
    public function unlock();

    /**
     * Executes a callback with write access to the context data.
     */
    public function synchronized(callable $callback);

    /**
     * Starts the context execution.
     */
    public function start();

    /**
     * Stops context execution.
     */
    public function stop();

    /**
     * Immediately kills the context without invoking any handlers.
     */
    public function kill();

    /**
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return PromiseInterface Promise that is resolved when the context finishes.
     */
    public function join();

    /**
     * Executes the context's main code.
     */
    public function run();
}
