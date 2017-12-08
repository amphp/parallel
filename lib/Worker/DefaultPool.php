<?php

namespace Amp\Parallel\Worker;

use Amp\CallableMaker;
use Amp\Parallel\StatusError;
use Amp\Promise;

/**
 * Provides a pool of workers that can be used to execute multiple tasks asynchronously.
 *
 * A worker pool is a collection of worker threads that can perform multiple
 * tasks simultaneously. The load on each worker is balanced such that tasks
 * are completed as soon as possible and workers are used efficiently.
 */
class DefaultPool implements Pool {
    use CallableMaker;

    /** @var bool Indicates if the pool is currently running. */
    private $running = false;

    /** @var int The minimum number of workers the pool should spawn. */
    private $minSize;

    /** @var int The maximum number of workers the pool should spawn. */
    private $maxSize;

    /** @var WorkerFactory A worker factory to be used to create new workers. */
    private $factory;

    /** @var \SplObjectStorage A collection of all workers in the pool. */
    private $workers;

    /** @var \SplQueue A collection of idle workers. */
    private $idleWorkers;

    /** @var \SplQueue A queue of workers that have been assigned to tasks. */
    private $busyQueue;

    /** @var \Closure */
    private $push;

    /**
     * Creates a new worker pool.
     *
     * @param int|null $minSize The minimum number of workers the pool should spawn.
     *     Defaults to `Pool::DEFAULT_MIN_SIZE`.
     * @param int|null $maxSize The maximum number of workers the pool should spawn.
     *     Defaults to `Pool::DEFAULT_MAX_SIZE`.
     * @param \Amp\Parallel\Worker\WorkerFactory|null $factory A worker factory to be used to create
     *     new workers.
     *
     * @throws \Error
     */
    public function __construct(int $minSize = null, int $maxSize = null, WorkerFactory $factory = null) {
        $minSize = $minSize ?: self::DEFAULT_MIN_SIZE;
        $maxSize = $maxSize ?: self::DEFAULT_MAX_SIZE;

        if ($minSize < 0) {
            throw new \Error('Minimum size must be a non-negative integer.');
        }

        if ($maxSize < 0 || $maxSize < $minSize) {
            throw new \Error('Maximum size must be a non-negative integer at least '.$minSize.'.');
        }

        $this->maxSize = $maxSize;
        $this->minSize = $minSize;

        // Use the global factory if none is given.
        $this->factory = $factory ?: factory();

        $this->workers = new \SplObjectStorage;
        $this->idleWorkers = new \SplQueue;
        $this->busyQueue = new \SplQueue;

        $this->push = $this->callableFromInstanceMethod("push");
    }

    /**
     * Checks if the pool is running.
     *
     * @return bool True if the pool is running, otherwise false.
     */
    public function isRunning(): bool {
        return $this->running;
    }

    /**
     * Checks if the pool has any idle workers.
     *
     * @return bool True if the pool has at least one idle worker, otherwise false.
     */
    public function isIdle(): bool {
        return $this->idleWorkers->count() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMinSize(): int {
        return $this->minSize;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxSize(): int {
        return $this->maxSize;
    }

    /**
     * {@inheritdoc}
     */
    public function getWorkerCount(): int {
        return $this->workers->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleWorkerCount(): int {
        return $this->idleWorkers->count();
    }

    /**
     * Starts the worker pool execution.
     *
     * When the worker pool starts up, the minimum number of workers will be created. This adds some overhead to
     * starting the pool, but allows for greater performance during runtime.
     */
    public function start() {
        if ($this->isRunning()) {
            throw new StatusError('The worker pool has already been started.');
        }

        // Start up the pool with the minimum number of workers.
        $count = $this->minSize;
        while (--$count >= 0) {
            $worker = $this->createWorker();
            $this->idleWorkers->enqueue($worker);
        }

        $this->running = true;
    }

    /**
     * Enqueues a task to be executed by the worker pool.
     *
     * @param Task $task The task to enqueue.
     *
     * @return \Amp\Promise<mixed> The return value of Task::run().
     *
     * @throws \Amp\Parallel\StatusError If the pool has not been started.
     * @throws \Amp\Parallel\Worker\TaskException If the task throws an exception.
     */
    public function enqueue(Task $task): Promise {
        $worker = $this->pull();

        $promise = $worker->enqueue($task);
        $promise->onResolve(function () use ($worker) {
            $this->push($worker);
        });
        return $promise;
    }

    /**
     * Shuts down the pool and all workers in it.
     *
     * @return \Amp\Promise<int[]> Array of exit status from all workers.
     *
     * @throws \Amp\Parallel\StatusError If the pool has not been started.
     */
    public function shutdown(): Promise {
        if (!$this->isRunning()) {
            throw new StatusError('The pool is not running.');
        }

        $this->running = false;

        $shutdowns = [];
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $shutdowns[] = $worker->shutdown();
            }
        }

        return Promise\all($shutdowns);
    }

    /**
     * Kills all workers in the pool and halts the worker pool.
     */
    public function kill() {
        $this->running = false;

        foreach ($this->workers as $worker) {
            $worker->kill();
        }
    }

    /**
     * Creates a worker and adds them to the pool.
     *
     * @return Worker The worker created.
     */
    private function createWorker() {
        $worker = $this->factory->create();
        $worker->start();

        $this->workers->attach($worker, 0);
        return $worker;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): Worker {
        return new Internal\PooledWorker($this->pull(), $this->push);
    }

    /**
     * Pulls a worker from the pool. The worker should be put back into the pool with push() to be marked as idle.
     *
     * @return \Amp\Parallel\Worker\Worker
     * @throws \Amp\Parallel\StatusError
     */
    protected function pull(): Worker {
        if (!$this->isRunning()) {
            throw new StatusError("The queue is not running");
        }

        do {
            if ($this->idleWorkers->isEmpty()) {
                if ($this->getWorkerCount() >= $this->maxSize) {
                    // All possible workers busy, so shift from head (will be pushed back onto tail below).
                    $worker = $this->busyQueue->shift();
                } else {
                    // Max worker count has not been reached, so create another worker.
                    $worker = $this->createWorker();
                }
            } else {
                // Shift a worker off the idle queue.
                $worker = $this->idleWorkers->shift();
            }

            if ($worker->isRunning()) {
                break;
            }

            $this->workers->detach($worker);
        } while (true);

        $this->busyQueue->push($worker);
        $this->workers[$worker] += 1;

        return $worker;
    }

    /**
     * Pushes the worker back into the queue.
     *
     * @param \Amp\Parallel\Worker\Worker $worker
     *
     * @throws \Error If the worker was not part of this queue.
     */
    protected function push(Worker $worker) {
        if (!$this->workers->contains($worker)) {
            throw new \Error("The provided worker was not part of this queue");
        }

        if (($this->workers[$worker] -= 1) === 0) {
            // Worker is completely idle, remove from busy queue and add to idle queue.
            foreach ($this->busyQueue as $key => $busy) {
                if ($busy === $worker) {
                    unset($this->busyQueue[$key]);
                    break;
                }
            }

            $this->idleWorkers->push($worker);
        }
    }
}
