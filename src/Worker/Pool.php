<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Exception\SynchronizationError;
use Icicle\Coroutine\Coroutine;
use Icicle\Promise;
use Icicle\Promise\Deferred;
use Icicle\Promise\PromiseInterface;

/**
 * Provides a pool of workers that can be used to execute multiple tasks asynchronously.
 *
 * A worker pool is a collection of worker threads that can perform multiple
 * tasks simultaneously. The load on each worker is balanced such that tasks
 * are completed as soon as possible and workers are used efficiently.
 */
class Pool implements WorkerInterface
{
    /**
     * @var int The default minimum pool size.
     */
    const DEFAULT_MIN_SIZE = 8;

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
     * @var WorkerFactoryInterface A worker factory to be used to create new workers.
     */
    private $factory;

    /**
     * @var \SplObjectStorage A collection of all workers in the pool.
     */
    private $workers;

    /**
     * @var \SplObjectStorage A collection of idle workers.
     */
    private $idleWorkers;

    /**
     * @var \SplQueue A queue of tasks waiting to be submitted.
     */
    private $busyQueue;

    /**
     * Creates a new worker pool.
     *
     * @param int|null                    $minSize The minimum number of workers the pool should spawn. Defaults to
     *                                             `Pool::DEFAULT_MIN_SIZE`.
     * @param int|null                    $maxSize The maximum number of workers the pool should spawn. Defaults to
     *                                             `Pool::DEFAULT_MAX_SIZE`.
     * @param WorkerFactoryInterface|null $factory A worker factory to be used to create new workers.
     */
    public function __construct($minSize = null, $maxSize = null, WorkerFactoryInterface $factory = null)
    {
        $minSize = $minSize ?: static::DEFAULT_MIN_SIZE;
        $maxSize = $minSize ?: static::DEFAULT_MAX_SIZE;

        if (!is_int($minSize) || $minSize < 0) {
            throw new InvalidArgumentError('Minimum size must be a non-negative integer.');
        }

        if (!is_int($maxSize) || $maxSize < 0 || $maxSize < $minSize) {
            throw new InvalidArgumentError('Maximum size must be a non-negative integer at least '.$minSize.'.');
        }

        $this->maxSize = $maxSize;
        $this->minSize = $minSize;

        // Create the default factory if none is given.
        $this->factory = $factory ?: new WorkerFactory();

        $this->workers = new \SplObjectStorage();
        $this->idleWorkers = new \SplObjectStorage();
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
     * Gets the number of workers currently running in the pool.
     *
     * @return int The number of workers.
     */
    public function getWorkerCount()
    {
        return $this->workers->count();
    }

    /**
     * Gets the number of workers that are currently idle.
     *
     * @return int The number of idle workers.
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
        // Start up the pool with the minimum number of workers.
        $count = $this->minSize;
        while (--$count >= 0) {
            $this->createWorker();
        }

        $this->running = true;
    }

    /**
     * Enqueues a task to be executed by the worker pool.
     *
     * @coroutine
     *
     * @param TaskInterface $task The task to enqueue.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of the task.
     */
    public function enqueue(TaskInterface $task /* , ...$args */)
    {
        if (!$this->running) {
            throw new SynchronizationError('The worker pool has not been started.');
        }

        $args = array_slice(func_get_args(), 1);

        // Enqueue the task if we have an idle worker.
        if ($worker = $this->getIdleWorker()) {
            yield $this->enqueueToWorker($worker, $task, $args);
            return;
        }

        // If we're at our limit of busy workers, add the task to the waiting list to be enqueued later when a new
        // worker becomes available.
        $deferred = new Deferred();
        $this->busyQueue->enqueue($task);
        $this->deferredQueue->enqueue($deferred);

        // Yield a promise that will be resolved when the task gets processed later.
        yield $deferred->getPromise();
    }

    /**
     * Shuts down the pool and all workers in it.
     *
     * @coroutine
     *
     * @return \Generator
     */
    public function shutdown()
    {
        $shutdowns = [];

        foreach ($this->workers as $worker) {
            $shutdowns[] = new Coroutine($worker->shutdown());
        }

        yield Promise\all($shutdowns);
        $this->running = false;
    }

    /**
     * Kills all workers in the pool and halts the worker pool.
     */
    public function kill()
    {
        foreach ($this->workers as $worker) {
            $worker->kill();
        }

        $this->running = false;
    }

    /**
     * Shuts down the pool when it is destroyed.
     */
    public function __destruct()
    {
        if ($this->isRunning()) {
            $coroutine = new Coroutine($this->shutdown());
            $coroutine->done();
        }
    }

    /**
     * Creates a worker and adds them to the pool.
     *
     * @return WorkerInterface The worker created.
     */
    private function createWorker()
    {
        $worker = $this->factory->create();
        $worker->start();

        $this->workers->attach($worker);
        $this->idleWorkers->attach($worker);
        return $worker;
    }

    /**
     * Gets the first available idle worker, or spawns a new worker if able.
     *
     * @return WorkerInterface|null An idle worker, or null if none could be found.
     */
    private function getIdleWorker()
    {
        // If there are idle workers, select the first one and return it.
        if ($this->idleWorkers->count() > 0) {
            $this->idleWorkers->rewind();
            return $this->idleWorkers->current();
        }

        // If there are no idle workers and we are allowed to spawn more, do so now.
        if ($this->getWorkerCount() < $this->maxSize) {
            return $this->createWorker();
        }
    }

    /**
     * Enqueues a task to a given worker.
     *
     * Waits for the task to finish, and resolves with the task's result. When the assigned worker becomes idle again,
     * a new coroutine is started to process the busy task queue if needed.
     *
     * @coroutine
     *
     * @param WorkerInterface $worker The worker to enqueue to.
     * @param TaskInterface   $task   The task to enqueue.
     * @param array           $args   An array of arguments to pass to the task.
     *
     * @return \Generator
     *
     * @resolve mixed The return value of the task.
     */
    private function enqueueToWorker(WorkerInterface $worker, TaskInterface $task, array $args = [])
    {
        $this->idleWorkers->detach($worker);
        yield call_user_func_array([$worker, 'enqueue'], array_merge([$task], $args));
        $this->idleWorkers->attach($worker);

        // Spawn a new coroutine to process the busy queue if not empty.
        if (!$this->busyQueue->isEmpty()) {
            $coroutine = new Coroutine($this->processBusyQueue());
            $coroutine->done();
        }
    }

    /**
     * Processes the busy queue until it is empty.
     *
     * @coroutine
     *
     * @return \Generator
     */
    private function processBusyQueue()
    {
        while (!$this->busyQueue->isEmpty()) {
            // If we cannot find an idle worker, give up like a wimp. (Don't worry, some other coroutine will pick up
            // the slack).
            if (!($worker = $this->getIdleWorker())) {
                break;
            }

            $task = $this->busyQueue->dequeue();
            $deferred = $this->deferredQueue->dequeue();

            try {
                $returnValue = (yield $this->enqueueToWorker($worker, $task));
                $deferred->resolve($returnValue);
            } catch (\Exception $exception) {
                $deferred->reject($exception);
            }
        }
    }
}
