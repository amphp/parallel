<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;
use Revolt\EventLoop;

/**
 * Gets or sets the global worker pool.
 *
 * @param Pool|null $pool A worker pool instance.
 *
 * @return Pool The global worker pool instance.
 */
function pool(Pool $pool = null): Pool
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($pool) {
        return $map[$driver] = $pool;
    }

    return $map[$driver] ??= new DefaultPool();
}

/**
 * Enqueues a task to be executed by the global worker pool.
 *
 * @param Task                   $task The task to enqueue.
 * @param CancellationToken|null $token
 *
 * @return mixed
 */
function enqueue(Task $task, ?CancellationToken $token = null): mixed
{
    return pool()->enqueue($task, $token);
}

/**
 * Enqueues a callable to be executed by the global worker pool.
 *
 * @param callable $callable Callable needs to be serializable.
 * @param mixed    ...$args Arguments have to be serializable.
 *
 * @return mixed
 */
function enqueueCallable(callable $callable, ...$args): mixed
{
    return enqueue(new CallableTask($callable, $args));
}

/**
 * Gets an available worker from the global worker pool.
 *
 * @return Worker
 */
function worker(): Worker
{
    return pool()->getWorker();
}

/**
 * Creates a worker using the global worker factory.
 *
 * @return Worker
 */
function create(): Worker
{
    return factory()->create();
}

/**
 * Gets or sets the global worker factory.
 *
 * @param WorkerFactory|null $factory
 *
 * @return WorkerFactory
 */
function factory(WorkerFactory $factory = null): WorkerFactory
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($factory) {
        return $map[$driver] = $factory;
    }

    return $map[$driver] ??= new DefaultWorkerFactory();
}
