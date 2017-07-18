<?php

namespace Amp\Parallel\Worker;

use Amp\Loop;
use Amp\Promise;

const LOOP_POOL_IDENTIFIER = Pool::class;
const LOOP_FACTORY_IDENTIFIER = WorkerFactory::class;

/**
 * Returns the global worker pool for the current context.
 *
 * @param \Amp\Parallel\Worker\Pool|null $pool A worker pool instance.
 *
 * @return \Amp\Parallel\Worker\Pool The global worker pool instance.
 */
function pool(Pool $pool = null): Pool {
    if ($pool === null) {
        $pool = Loop::getState(LOOP_POOL_IDENTIFIER);
        if ($pool) {
            return $pool;
        }

        $pool = new DefaultPool;
    }

    Loop::setState(LOOP_POOL_IDENTIFIER, $pool);
    return $pool;
}

/**
 * Enqueues a task to be executed by the global worker pool.
 *
 * @param \Amp\Parallel\Worker\Task $task The task to enqueue.
 *
 * @return \Amp\Promise<mixed>
 */
function enqueue(Task $task): Promise {
    $pool = pool();

    if (!$pool->isRunning()) {
        $pool->start();
    }

    return $pool->enqueue($task);
}

/**
 * Creates a worker using the global worker factory.
 *
 * @return \Amp\Parallel\Worker\Worker
 */
function create(): Worker {
    $worker = factory()->create();
    $worker->start();
    return $worker;
}

/**
 * Gets or sets the global worker factory.
 *
 * @param \Amp\Parallel\Worker\WorkerFactory|null $factory
 *
 * @return \Amp\Parallel\Worker\WorkerFactory
 */
function factory(WorkerFactory $factory = null): WorkerFactory {
    if ($factory === null) {
        $factory = Loop::getState(LOOP_FACTORY_IDENTIFIER);
        if ($factory) {
            return $factory;
        }

        $factory = new DefaultWorkerFactory;
    }
    Loop::setState(LOOP_FACTORY_IDENTIFIER, $factory);
    return $factory;
}

/**
 * Gets a worker from the global worker pool.
 *
 * @return \Amp\Parallel\Worker\Worker
 */
function get(): Worker {
    $pool = pool();

    if (!$pool->isRunning()) {
        $pool->start();
    }

    return $pool->get();
}
