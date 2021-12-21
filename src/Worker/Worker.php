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
     * @template TReceive
     * @template TSend
     *
     * Executes a {@see Task} on the worker.
     *
     * @param Task<TResult> $task The task to execute.
     * @param Cancellation|null $cancellation Token to request cancellation. The task must support cancellation for
     * this to have any effect.
     *
     * @return Job<TResult, TReceive, TSend>
     */
    public function enqueue(Task $task, ?Cancellation $cancellation = null): Job;

    /**
     * @return int Returns the exit code when the worker successfully shuts down.
     */
    public function shutdown(): int;

    /**
     * Immediately kills the context.
     */
    public function kill();
}
