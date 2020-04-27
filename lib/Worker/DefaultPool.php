<?php

namespace Amp\Parallel\Worker;

use Amp\Deferred;
use Amp\Parallel\Context\StatusError;
use Amp\Promise;
use function Amp\asyncCall;
use function Amp\call;

/**
 * Provides a pool of workers that can be used to execute multiple tasks asynchronously.
 *
 * A worker pool is a collection of worker threads that can perform multiple
 * tasks simultaneously. The load on each worker is balanced such that tasks
 * are completed as soon as possible and workers are used efficiently.
 */
final class DefaultPool implements Pool
{
    /** @var bool Indicates if the pool is currently running. */
    private $running = true;

    /** @var int The maximum number of workers the pool should spawn. */
    private $maxSize;

    /** @var WorkerFactory A worker factory to be used to create new workers. */
    private $factory;

    /** @var \SplObjectStorage A collection of all workers in the pool. */
    private $workers;

    /** @var \SplQueue A collection of idle workers. */
    private $idleWorkers;

    /** @var Deferred|null */
    private $waiting;

    /** @var \Closure */
    private $push;

    /** @var Promise|null */
    private $exitStatus;

    /**
     * Creates a new worker pool.
     *
     * @param int $maxSize The maximum number of workers the pool should spawn.
     *     Defaults to `Pool::DEFAULT_MAX_SIZE`.
     * @param WorkerFactory|null $factory A worker factory to be used to create
     *     new workers.
     *
     * @throws \Error
     */
    public function __construct(int $maxSize = self::DEFAULT_MAX_SIZE, WorkerFactory $factory = null)
    {
        if ($maxSize < 0) {
            throw new \Error("Maximum size must be a non-negative integer");
        }

        $this->maxSize = $maxSize;

        // Use the global factory if none is given.
        $this->factory = $factory ?? factory();

        $this->workers = new \SplObjectStorage;
        $this->idleWorkers = new \SplQueue;

        $workers = $this->workers;
        $idleWorkers = $this->idleWorkers;
        $waiting = &$this->waiting;

        $this->push = static function (Worker $worker) use (&$waiting, $workers, $idleWorkers): void {
            if (!$workers->contains($worker)) {
                return;
            }

            $idleWorkers->push($worker);

            if ($waiting !== null) {
                $deferred = $waiting;
                $waiting = null;
                $deferred->resolve($worker);
            }
        };
    }

    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->kill();
        }
    }

    /**
     * Checks if the pool is running.
     *
     * @return bool True if the pool is running, otherwise false.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Checks if the pool has any idle workers.
     *
     * @return bool True if the pool has at least one idle worker, otherwise false.
     */
    public function isIdle(): bool
    {
        return $this->idleWorkers->count() > 0 || $this->workers->count() === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * {@inheritdoc}
     */
    public function getWorkerCount(): int
    {
        return $this->workers->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleWorkerCount(): int
    {
        return $this->idleWorkers->count();
    }

    /**
     * Enqueues a {@see Task} to be executed by the worker pool.
     *
     * @param Task $task The task to enqueue.
     *
     * @return Promise<mixed> The return value of Task::run().
     *
     * @throws StatusError If the pool has been shutdown.
     * @throws TaskFailureThrowable If the task throws an exception.
     */
    public function enqueue(Task $task): Promise
    {
        return call(function () use ($task): \Generator {
            $worker = yield from $this->pull();

            try {
                $result = yield $worker->enqueue($task);
            } finally {
                ($this->push)($worker);
            }

            return $result;
        });
    }

    /**
     * Shuts down the pool and all workers in it.
     *
     * @return Promise<int[]> Array of exit status from all workers.
     *
     * @throws StatusError If the pool has not been started.
     */
    public function shutdown(): Promise
    {
        if ($this->exitStatus) {
            return $this->exitStatus;
        }

        $this->running = false;

        $shutdowns = [];
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $shutdowns[] = $worker->shutdown();
            }
        }

        if ($this->waiting !== null) {
            $deferred = $this->waiting;
            $this->waiting = null;
            $deferred->fail(new WorkerException('The pool shutdown before the task could be executed'));
        }

        return $this->exitStatus = Promise\all($shutdowns);
    }

    /**
     * Kills all workers in the pool and halts the worker pool.
     */
    public function kill(): void
    {
        $this->running = false;

        foreach ($this->workers as $worker) {
            \assert($worker instanceof Worker);
            if ($worker->isRunning()) {
                $worker->kill();
            }
        }

        if ($this->waiting !== null) {
            $deferred = $this->waiting;
            $this->waiting = null;
            $deferred->fail(new WorkerException('The pool was killed before the task could be executed'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getWorker(): Promise
    {
        return call(function (): \Generator {
            return new Internal\PooledWorker(yield from $this->pull(), $this->push);
        });
    }

    /**
     * Pulls a worker from the pool.
     *
     * @return \Generator
     *
     * @throws StatusError
     */
    private function pull(): \Generator
    {
        if (!$this->isRunning()) {
            throw new StatusError("The pool was shutdown");
        }

        do {
            if ($this->idleWorkers->isEmpty()) {
                if ($this->getWorkerCount() < $this->maxSize) {
                    // Max worker count has not been reached, so create another worker.
                    $worker = $this->factory->create();
                    if (!$worker->isRunning()) {
                        throw new WorkerException('Worker factory did not create a viable worker');
                    }
                    $this->workers->attach($worker, 0);
                    return $worker;
                }

                if ($this->waiting === null) {
                    $this->waiting = new Deferred;
                }

                do {
                    $worker = yield $this->waiting->promise();
                } while ($this->waiting !== null);
            } else {
                // Shift a worker off the idle queue.
                $worker = $this->idleWorkers->shift();
            }

            \assert($worker instanceof Worker);

            if ($worker->isRunning()) {
                return $worker;
            }

            // Worker crashed; trigger error and remove it from the pool.

            asyncCall(function () use ($worker): \Generator {
                try {
                    $code = yield $worker->shutdown();
                    \trigger_error('Worker in pool exited unexpectedly with code ' . $code, \E_USER_WARNING);
                } catch (\Throwable $exception) {
                    \trigger_error(
                        'Worker in pool crashed with exception on shutdown: ' . $exception->getMessage(),
                        \E_USER_WARNING
                    );
                }
            });

            $this->workers->detach($worker);
        } while (true);
    }
}
