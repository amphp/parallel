<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;

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
     * @template TResult
     *
     * Executes a {@see Task} on the worker.
     *
     * @param Task<TResult> $task The task to execute.
     * @param Cancellation|null $cancellation Token to request cancellation. The task must support cancellation for
     * this to have any effect.
     *
     * @return TResult The return value of {@see Task::run()}.
     *
     * @throws TaskFailureThrowable Promise fails if {@see Task::run()} throws an exception.
     */
    public function execute(Task $task, ?Cancellation $cancellation = null): mixed;

    /**
     * @return int Returns the exit code when the worker successfully shuts down.
     */
    public function shutdown(): int;

    /**
     * Immediately kills the context.
     */
    public function kill();
}
