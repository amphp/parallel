<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Coroutine\Coroutine;

if (!function_exists(__NAMESPACE__ . '\pool')) {
    /**
     * Returns the global worker pool for the current context.
     *
     * If the pool has not been initialized, a minimum and maximum size can be given to create the pool with.
     *
     * @param int|null                    $minSize The minimum number of workers the pool should spawn.
     * @param int|null                    $maxSize The maximum number of workers the pool should spawn.
     * @param WorkerFactoryInterface|null $factory A worker factory to be used to create new workers.
     *
     * @return Pool The global worker pool instance.
     */
    function pool($minSize = null, $maxSize = null, WorkerFactoryInterface $factory = null)
    {
        static $instance;

        if (null === $instance) {
            $instance = new Pool($minSize, $maxSize, $factory);
            $instance->start();
        }

        return $instance;
    }

    /**
     * Enqueues a task to be executed by the worker pool.
     *
     * @param TaskInterface $task The task to enqueue.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve mixed The return value of the task.
     */
    function enqueue(TaskInterface $task)
    {
        return new Coroutine(pool()->enqueue($task));
    }
}
