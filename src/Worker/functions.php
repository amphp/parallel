<?php
namespace Icicle\Concurrent\Worker;

if (!function_exists(__NAMESPACE__ . '\pool')) {
    /**
     * Returns the default worker pool for the current context.
     *
     * If the pool has not been initialized, a minimum and maximum size can be given to create the pool with.
     *
     * @param int|null                    $minSize The minimum number of workers the pool should spawn.
     * @param int|null                    $maxSize The maximum number of workers the pool should spawn.
     * @param WorkerFactoryInterface|null $factory A worker factory to be used to create new workers.
     *
     * @return WorkerPool
     */
    function pool($minSize = null, $maxSize = null, WorkerFactoryInterface $factory = null)
    {
        static $instance;

        if (null === $instance) {
            if (null !== $minSize) {
                $instance = new WorkerPool($minSize, $maxSize, $factory);
            } else {
                $instance = new WorkerPool(8, 32);
            }
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
