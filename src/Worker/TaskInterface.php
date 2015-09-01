<?php
namespace Icicle\Concurrent\Worker;

/**
 * A runnable unit of execution.
 */
interface TaskInterface
{
    /**
     * @coroutine
     *
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     * @return \Generator
     *
     * @resolve mixed
     */
    public function run();
}
