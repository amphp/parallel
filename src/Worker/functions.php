<?php
namespace Icicle\Concurrent\Worker;

if (!function_exists(__NAMESPACE__ . '\pool')) {
    /**
     * Returns the global worker pool for the current context.
     *
     * @param \Icicle\Concurrent\Worker\Pool|null $pool A worker pool instance.
     *
     * @return \Icicle\Concurrent\Worker\Pool The global worker pool instance.
     */
    function pool(Pool $pool = null)
    {
        static $instance;

        if (null !== $pool) {
            $instance = $pool;
        } elseif (null === $instance) {
            $instance = new DefaultPool();
        }

        if (!$instance->isRunning()) {
            $instance->start();
        }

        return $instance;
    }

    /**
     * @coroutine
     *
     * Enqueues a task to be executed by the global worker pool.
     *
     * @param \Icicle\Concurrent\Worker\Task $task The task to enqueue.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of the task.
     */
    function enqueue(Task $task)
    {
        yield pool()->enqueue($task);
    }

    /**
     * Creates a worker using the global worker factory.
     *
     * @return \Icicle\Concurrent\Worker\Worker
     */
    function create()
    {
        $worker = factory()->create();
        $worker->start();
        return $worker;
    }

    /**
     * Gets or sets the global worker factory.
     *
     * @param \Icicle\Concurrent\Worker\WorkerFactory|null $factory
     *
     * @return \Icicle\Concurrent\Worker\WorkerFactory
     */
    function factory(WorkerFactory $factory = null)
    {
        static $instance;

        if (null !== $factory) {
            $instance = $factory;
        } elseif (null === $instance) {
            $instance = new DefaultWorkerFactory();
        }

        return $instance;
    }

    /**
     * Gets a worker from the global worker pool.
     *
     * @return \Icicle\Concurrent\Worker\Worker
     */
    function get()
    {
        return pool()->get();
    }
}
