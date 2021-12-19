<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Revolt\EventLoop;

/**
 * Gets or sets the global worker pool.
 *
 * @param Pool|null $pool A worker pool instance.
 *
 * @return Pool The global worker pool instance.
 */
function pool(?Pool $pool = null): Pool
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
 * @template TResult
 *
 * Executes a Task on the global worker pool.
 *
 * @param Task<TResult> $task The task to enqueue.
 * @param Cancellation|null $cancellation
 *
 * @return TResult
 * @throws TaskFailureThrowable
 */
function execute(Task $task, ?Cancellation $cancellation = null): mixed
{
    return pool()->execute($task, $cancellation);
}

/**
 * Gets an available worker from the global worker pool.
 *
 * @return Worker
 */
function pooledWorker(): Worker
{
    return pool()->getWorker();
}

/**
 * Creates a worker using the global worker factory.
 *
 * @return Worker
 */
function createWorker(): Worker
{
    return workerFactory()->create();
}

/**
 * Gets or sets the global worker factory.
 *
 * @param WorkerFactory|null $factory
 *
 * @return WorkerFactory
 */
function workerFactory(WorkerFactory $factory = null): WorkerFactory
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($factory) {
        return $map[$driver] = $factory;
    }

    return $map[$driver] ??= new DefaultWorkerFactory();
}
