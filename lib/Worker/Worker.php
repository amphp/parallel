<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * An interface for a parallel worker thread that runs a queue of tasks.
 */
interface Worker
{
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
     * Enqueues a {@see Task} to be executed by the worker.
     *
     * @param Task $task The task to enqueue.
     * @param CancellationToken|null $token Token to request cancellation. The task must support cancellation for this
     *                                      to have any effect.
     *
     * @return Promise<mixed> Resolves with the return value of {@see Task::run()}.
     *
     * @throws TaskFailureThrowable Promise fails if {@see Task::run()} throws an exception.
     */
    public function enqueue(Task $task, ?CancellationToken $token = null): Promise;

    /**
     * @return Promise<void> Resolves when the worker successfully shuts down.
     */
    public function shutdown(): Promise;

    /**
     * Immediately kills the context.
     */
    public function kill();
}
