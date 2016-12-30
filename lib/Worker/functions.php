<?php

namespace Amp\Parallel\Worker;

use Interop\Async\Promise;

/**
 * Returns the global worker pool for the current context.
 *
 * @param \Amp\Parallel\Worker\Pool|null $pool A worker pool instance.
 *
 * @return \Amp\Parallel\Worker\Pool The global worker pool instance.
 */
function pool(Pool $pool = null): Pool {
    static $instance;

    if (null !== $pool) {
        $instance = $pool;
    } elseif (null === $instance) {
        $instance = new DefaultPool;
    }

    if (!$instance->isRunning()) {
        $instance->start();
    }

    return $instance;
}

/**
 * Enqueues a task to be executed by the global worker pool.
 *
 * @param \Amp\Parallel\Worker\Task $task The task to enqueue.
 *
 * @return \Interop\Async\Promise<mixed>
 */
function enqueue(Task $task): Promise {
    return pool()->enqueue($task);
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
    static $instance;

    if (null !== $factory) {
        $instance = $factory;
    } elseif (null === $instance) {
        $instance = new DefaultWorkerFactory;
    }

    return $instance;
}

/**
 * Gets a worker from the global worker pool.
 *
 * @return \Amp\Parallel\Worker\Worker
 */
function get(): Worker {
    return pool()->get();
}
