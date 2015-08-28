<?php
namespace Icicle\Concurrent\Worker;

if (!function_exists(__NAMESPACE__ . '\pool')) {
    /**
     * Returns the default worker pool for the current context.
     *
     * @param WorkerPool $pool The instance to use as the default worker pool.
     *
     * @return WorkerPool
     */
    function pool(WorkerPool $pool = null)
    {
        static $instance;

        if (null !== $pool) {
            $instance = $pool;
        } elseif (null === $instance) {
            $instance = new WorkerPool(4, 16);
        }

        return $instance;
    }

    /**
     * Enqueues a task to be executed by the worker pool.
     *
     * @coroutine
     *
     * @param TaskInterface $task The task to enqueue.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of the task.
     */
    function enqueue(TaskInterface $task)
    {
        return pool()->enqueue($task);
    }
}
