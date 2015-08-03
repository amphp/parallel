<?php
namespace Icicle\Concurrent;

/**
 * Interface for all types of execution contexts.
 */
interface ContextInterface extends SynchronizableInterface
{
    /**
     * Creates a new context with a given function to run.
     *
     * @return ContextInterface A context instance.
     */
    //public static function create(callable $function);

    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning();

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
     * Causes the context to immediately panic.
     *
     * @param string $message A panic message.
     * @param int $code A panic code.
     */
    public function panic($message = '', $code = 0);

    /**
     * Gets a promise that resolves when the context ends and joins with the
     * parent context.
     *
     * @return \Icicle\Promise\PromiseInterface Promise that is resolved when the context finishes.
     */
    public function join();
}
