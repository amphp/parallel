<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\WorkerException;
use Icicle\Coroutine\Coroutine;

/**
 * Provides a pool of workers that can be used to execute multiple tasks asynchronously.
 *
 * A worker pool is a collection of worker threads that can perform multiple
 * tasks simultaneously. The load on each worker is balanced such that tasks
 * are completed as soon as possible and workers are used efficiently.
 */
class DefaultPool implements Pool
{
    /**
     * @var int The default minimum pool size.
     */
    const DEFAULT_MIN_SIZE = 4;

    /**
     * @var int The default maximum pool size.
     */
    const DEFAULT_MAX_SIZE = 32;

    /**
     * @var bool Indicates if the pool is currently running.
     */
    private $running = false;

    /**
     * @var int The minimum number of workers the pool should spawn.
     */
    private $minSize;

    /**
     * @var int The maximum number of workers the pool should spawn.
     */
    private $maxSize;

    /**
     * @var WorkerFactory A worker factory to be used to create new workers.
     */
    private $factory;

    /**
     * @var \SplObjectStorage A collection of all workers in the pool.
     */
    private $workers;

    /**
     * @var \SplQueue A collection of idle workers.
     */
    private $idleWorkers;

    /**
     * @var \SplQueue A queue of tasks waiting to be submitted.
     */
    private $busyQueue;

    /**
     * Creates a new worker pool.
     *
     * @param int $minSize The minimum number of workers the pool should spawn. Defaults to `Pool::DEFAULT_MIN_SIZE`.
     * @param int $maxSize The maximum number of workers the pool should spawn. Defaults to `Pool::DEFAULT_MAX_SIZE`.
     * @param \Icicle\Concurrent\Worker\WorkerFactory|null $factory A worker factory to be used to create
     *     new workers.
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    public function __construct($minSize = 0, $maxSize = 0, WorkerFactory $factory = null)
    {
        $minSize = $minSize ?: self::DEFAULT_MIN_SIZE;
        $maxSize = $maxSize ?: self::DEFAULT_MAX_SIZE;

        if (!is_int($minSize) || $minSize < 0) {
            throw new InvalidArgumentError('Minimum size must be a non-negative integer.');
        }

        if (!is_int($maxSize) || $maxSize < 0 || $maxSize < $minSize) {
            throw new InvalidArgumentError('Maximum size must be a non-negative integer at least '.$minSize.'.');
        }

        $this->maxSize = $maxSize;
        $this->minSize = $minSize;

        // Create the default factory if none is given.
        $this->factory = $factory ?: new DefaultWorkerFactory();

        $this->workers = new \SplObjectStorage();
        $this->idleWorkers = new \SplQueue();
        $this->busyQueue = new \SplQueue();
    }

    /**
     * Checks if the pool is running.
     *
     * @return bool True if the pool is running, otherwise false.
     */
    public function isRunning()
    {
        return $this->running;
    }

    /**
     * Checks if the pool has any idle workers.
     *
     * @return bool True if the pool has at least one idle worker, otherwise false.
     */
    public function isIdle()
    {
        return $this->idleWorkers->count() > 0;
    }

    /**
     * Gets the minimum number of workers the pool may have idle.
     *
     * @return int The minimum number of workers.
     */
    public function getMinSize()
    {
        return $this->minSize;
    }

    /**
     * Gets the maximum number of workers the pool may spawn to handle concurrent tasks.
     *
     * @return int The maximum number of workers.
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }

    /**
     * {@inheritdoc}
     */
    public function getWorkerCount()
    {
        return count($this->workers);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleWorkerCount()
    {
        return $this->idleWorkers->count();
    }

    /**
     * Starts the worker pool execution.
     *
     * When the worker pool starts up, the minimum number of workers will be created. This adds some overhead to
     * starting the pool, but allows for greater performance during runtime.
     */
    public function start()
    {
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
     * @coroutine
     *
     * @param Task $task The task to enqueue.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of the task.
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the pool has not been started.
     * @throws \Icicle\Concurrent\Exception\TaskException If the task throws an exception.
     */
    public function enqueue(Task $task)
    {
        if (!$this->isRunning()) {
            throw new StatusError('The worker pool has not been started.');
        }

        // Find a free worker if one is available.
        if (!$this->idleWorkers->isEmpty()) {
            $worker = $this->idleWorkers->dequeue();
        } elseif ($this->getWorkerCount() < $this->maxSize) {
            // We are allowed to spawn another worker if needed, so do so.
            $worker = $this->createWorker();
        } else {
            // We have no other choice but to wait for a worker to be freed up.
            $delayed = new Delayed();
            $this->busyQueue->enqueue($delayed);
            $worker = (yield $delayed);
        }

        try {
            $result = (yield $worker->enqueue($task));
        } finally {
            if (!$worker->isRunning()) {
                // Worker crashed, discard it and spin up a new worker.
                $this->workers->detach($worker);
                $worker = $this->createWorker();
            }

            // If someone is waiting on a worker, give it to them instead.
            if (!$this->busyQueue->isEmpty()) {
                /** @var \Icicle\Awaitable\Delayed $delayed */
                $delayed = $this->busyQueue->dequeue();

                if ($delayed->isPending()) {
                    $delayed->resolve($worker);
                }
            } else {
                // No tasks are waiting, so add the worker to the idle queue.
                $this->idleWorkers->enqueue($worker);
            }
        }

        yield $result;
    }

    /**
     * Shuts down the pool and all workers in it.
     *
     * @coroutine
     *
     * @return \Generator
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the pool has not been started.
     */
    public function shutdown()
    {
        if (!$this->isRunning()) {
            throw new StatusError('The pool is not running.');
        }

        $this->close();

        $shutdowns = [];

        foreach ($this->workers as $worker) {
            $shutdowns[] = new Coroutine($worker->shutdown());
        }

        yield Awaitable\reduce($shutdowns, function ($carry, $value) {
            return $carry ?: $value;
        }, 0);
    }

    /**
     * Kills all workers in the pool and halts the worker pool.
     */
    public function kill()
    {
        $this->close();

        foreach ($this->workers as $worker) {
            $worker->kill();
        }
    }

    /**
     * Rejects any queued tasks.
     */
    private function close()
    {
        $this->running = false;

        $exception = new WorkerException('Worker pool was shut down.');

        while (!$this->busyQueue->isEmpty()) {
            /** @var \Icicle\Awaitable\Delayed $delayed */
            $delayed = $this->busyQueue->dequeue();
            $delayed->cancel($exception);
        }
    }

    /**
     * Creates a worker and adds them to the pool.
     *
     * @return Worker The worker created.
     */
    private function createWorker()
    {
        $worker = $this->factory->create();
        $worker->start();

        $this->workers->attach($worker);
        return $worker;
    }
}
