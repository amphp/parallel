<?php

namespace Amp\Parallel\Worker;

use AsyncInterop\Promise;

/**
 * An interface for a parallel worker thread that runs a queue of tasks.
 */
interface Worker {
    /**
     * Checks if the worker is running.
     *
     * @return bool True if the worker is running, otherwise false.
     */
    public function isRunning(): bool;

    /**
     * Checks if the worker is currently idle.
     *
     * @return bool
     */
    public function isIdle(): bool;

    /**
     * Starts the context execution.
     */
    public function start();

    /**
     * Enqueues a task to be executed by the worker.
     *
     * @param Task $task The task to enqueue.
     *
     * @return \AsyncInterop\Promise<mixed> Resolves with the return value of Task::run().
     */
    public function enqueue(Task $task): Promise;

    /**
     * @return \AsyncInterop\Promise<int> Exit code.
     */
    public function shutdown(): Promise;

    /**
     * Immediately kills the context.
     */
    public function kill();
}
