<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Coroutine\Coroutine;

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
     * Enqueues a task to be executed by the worker pool.
     *
     * @param TaskInterface $task The task to enqueue.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve mixed The return value of the task.
     */
    function enqueue(TaskInterface $task /* , ...$args */)
    {
        return new Coroutine(call_user_func_array([pool(), 'enqueue'], func_get_args()));
    }
}
