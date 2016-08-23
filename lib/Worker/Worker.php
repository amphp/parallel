<?php declare(strict_types = 1);

namespace Amp\Parallel\Worker;

use Interop\Async\Awaitable;

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
     * @return \Interop\Async\Awaitable<mixed> Resolves with the return value of Task::run().
     */
    public function enqueue(Task $task): Awaitable;

    /**
     * @return \Interop\Async\Awaitable<int> Exit code.
     */
    public function shutdown(): Awaitable;

    /**
     * Immediately kills the context.
     */
    public function kill();
}
