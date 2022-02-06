<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Revolt\EventLoop;

/**
 * Gets or sets the global worker pool.
 *
 * @param WorkerPool|null $pool A worker pool instance.
 *
 * @return WorkerPool The global worker pool instance.
 */
function workerPool(?WorkerPool $pool = null): WorkerPool
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($pool) {
        return $map[$driver] = $pool;
    }

    return $map[$driver] ??= new DefaultWorkerPool();
}

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template TCache
 *
 * Executes a {@see Task} on the global worker pool.
 *
 * @param Task<TResult, TReceive, TSend, TCache> $task The task to execute.
 * @param Cancellation|null $cancellation Token to request cancellation. The task must support cancellation for
 * this to have any effect.
 *
 * @return Job<TResult, TReceive, TSend>
 */
function enqueue(Task $task, ?Cancellation $cancellation = null): Job
{
    return workerPool()->enqueue($task, $cancellation);
}

/**
 * Gets an available worker from the global worker pool.
 *
 * @return Worker
 */
function pooledWorker(): Worker
{
    return workerPool()->getWorker();
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
