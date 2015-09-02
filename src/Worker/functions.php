<?php
namespace Icicle\Concurrent\Worker;

if (!function_exists(__NAMESPACE__ . '\pool')) {
    /**
     * Returns the global worker pool for the current context.
     *
     * @param PoolInterface|null $pool A worker pool instance.
     *
     * @return PoolInterface The global worker pool instance.
     */
    function pool(PoolInterface $pool = null)
    {
        static $instance;

        if (null !== $pool) {
            $instance = $pool;
        } elseif (null === $instance) {
            $instance = new Pool();
        }

        if (!$instance->isRunning()) {
            $instance->start();
        }

        return $instance;
    }

    /**
     * @coroutine
     *
     * Enqueues a task to be executed by the worker pool.
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

    /**
     * @param \Icicle\Concurrent\Worker\WorkerFactoryInterface|null $factory
     *
     * @return \Icicle\Concurrent\Worker\WorkerInterface
     */
    function create(WorkerFactoryInterface $factory = null)
    {
        static $instance;

        if (null !== $factory) {
            $instance = $factory;
        } elseif (null === $instance) {
            $instance = new WorkerFactory();
        }

        $worker = $instance->create();
        $worker->start();
        return $worker;
    }
}
