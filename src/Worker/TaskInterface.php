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
     * Can accept a varied number of arguments sent by the worker.
     *
     * @param ...mixed $args
     *
     * @return \Generator
     *
     * @resolve mixed
     */
    public function run(/* ...$args */);
}
