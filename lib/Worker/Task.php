<?php declare(strict_types = 1);

namespace Amp\Concurrent\Worker;

/**
 * A runnable unit of execution.
 */
interface Task {
    /**
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     * @param \Amp\Concurrent\Worker\Environment
     *
     * @return mixed|\Interop\Async\Awaitable|\Generator
     */
    public function run(Environment $environment);
}
