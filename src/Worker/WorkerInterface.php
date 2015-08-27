<?php
namespace Icicle\Concurrent\Worker;

/**
 * An interface for a parallel worker thread that runs a queue of tasks.
 */
interface WorkerInterface
{
    /**
     * Checks if the context is running.
     *
     * @return bool True if the context is running, otherwise false.
     */
    public function isRunning();

    /**
     * Checks if the worker is currently idle.
     *
     * @return bool
     */
    public function isIdle();

    /**
     * Starts the context execution.
     */
    public function start();

    /**
     * Immediately kills the context.
     */
    public function kill();

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve int Exit code.
     */
    public function shutdown();

    /**
     * @coroutine
     *
     * Enqueues a task to be executed by the worker.
     *
     * @param TaskInterface $task The task to enqueue.
     *
     * @return \Generator
     *
     * @resolve mixed Task return value.
     */
    public function enqueue(TaskInterface $task);
}
