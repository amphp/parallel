<?php
namespace Icicle\Concurrent\Worker;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * @coroutine
     *
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     * @param \Icicle\Concurrent\Worker\Environment
     *
     * @return \Generator
     *
     * @resolve mixed
     */
    public function run(Environment $environment);
}
