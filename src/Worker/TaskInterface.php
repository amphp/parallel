<?php
namespace Icicle\Concurrent\Worker;

/**
 * A runnable unit of execution.
 */
interface TaskInterface
{
    /**
     * Runs the task inside the caller's context.
     *
     * Can accept a varied number of arguments sent by the worker.
     */
    public function run();
}
