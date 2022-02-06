<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Parallel\Context\StatusError;
use Revolt\EventLoop;
use function Amp\async;

/**
 * Provides a pool of workers that can be used to execute multiple tasks asynchronously.
 *
 * A worker pool is a collection of worker threads that can perform multiple
 * tasks simultaneously. The load on each worker is balanced such that tasks
 * are completed as soon as possible and workers are used efficiently.
 */
final class DefaultWorkerPool implements WorkerPool
{
    /** @var bool Indicates if the pool is currently running. */
    private bool $running = true;

    /** @var int The maximum number of workers the pool should spawn. */
    private int $limit;

    /** @var WorkerFactory A worker factory to be used to create new workers. */
    private WorkerFactory $factory;

    /** @var \SplObjectStorage A collection of all workers in the pool. */
    private \SplObjectStorage $workers;

    /** @var \SplQueue A collection of idle workers. */
    private \SplQueue $idleWorkers;

    private ?DeferredFuture $waiting = null;

    private \Closure $push;

    private ?Future $exitStatus = null;

    /**
     * Creates a new worker pool.
     *
     * @param int $limit The maximum number of workers the pool should spawn.
     *     Defaults to `Pool::DEFAULT_MAX_SIZE`.
     * @param WorkerFactory|null $factory A worker factory to be used to create
     *     new workers.
     *
     * @throws \Error
     */
    public function __construct(int $limit = self::DEFAULT_WORKER_LIMIT, ?WorkerFactory $factory = null)
    {
        if ($limit < 0) {
            throw new \Error("Maximum size must be a non-negative integer");
        }

        $this->limit = $limit;

        // Use the global factory if none is given.
        $this->factory = $factory ?? workerFactory();

        $this->workers = new \SplObjectStorage;
        $this->idleWorkers = new \SplQueue;

        $workers = $this->workers;
        $idleWorkers = $this->idleWorkers;
        $waiting = &$this->waiting;

        $this->push = static function (Worker $worker) use (&$waiting, $workers, $idleWorkers): void {
            /** @psalm-suppress InvalidArgument */
            if (!$workers->contains($worker)) {
                return;
            }

            $idleWorkers->push($worker);
            $waiting?->complete($worker);
            $waiting = null;
        };
    }

    public function __destruct()
    {
        if ($this->isRunning()) {
            self::killWorkers($this->workers, $this->waiting);
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
        return $this->idleWorkers->count() > 0 || $this->workers->count() < $this->limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getWorkerCount(): int
    {
        return $this->workers->count();
    }

    public function getIdleWorkerCount(): int
    {
        return $this->idleWorkers->count();
    }

    /**
     * Submits a {@see Task} to be executed by the worker pool.
     */
    public function submit(Task $task, ?Cancellation $cancellation = null): Execution
    {
        $worker = $this->pull();
        $push = $this->push;

        try {
            $job = $worker->submit($task, $cancellation);
        } catch (\Throwable $exception) {
            $push($worker);
            throw $exception;
        }

        $job->getResult()->finally(static fn () => $push($worker))->ignore();

        return $job;
    }

    /**
     * Shuts down the pool and all workers in it.
     *
     * @return int
     *
     * @throws StatusError If the pool has not been started.
     */
    public function shutdown(): void
    {
        if ($this->exitStatus) {
            $this->exitStatus->await();
            return;
        }

        $this->running = false;

        $this->waiting?->error(new WorkerException('The pool shut down before the task could be executed'));
        $this->waiting = null;

        $workers = $this->workers;
        ($this->exitStatus = async(static function () use ($workers): void {
            foreach ($workers as $worker) {
                \assert($worker instanceof Worker);
                if ($worker->isRunning()) {
                    $worker->shutdown();
                }
            }
        }))->await();
    }

    /**
     * Kills all workers in the pool and halts the worker pool.
     */
    public function kill(): void
    {
        $this->running = false;
        self::killWorkers($this->workers, $this->waiting);
        $this->waiting = null;
    }

    private static function killWorkers(\SplObjectStorage $workers, ?DeferredFuture $waiting): void
    {
        foreach ($workers as $worker) {
            \assert($worker instanceof Worker);
            if ($worker->isRunning()) {
                $worker->kill();
            }
        }

        $waiting?->error(new WorkerException('The pool was killed before the task could be executed'));
    }

    public function getWorker(): Worker
    {
        return new Internal\PooledWorker($this->pull(), $this->push);
    }

    /**
     * Pulls a worker from the pool.
     *
     * @throws StatusError
     * @throws WorkerException
     */
    private function pull(): Worker
    {
        if (!$this->isRunning()) {
            throw new StatusError("The pool was shut down");
        }

        do {
            if ($this->idleWorkers->isEmpty()) {
                if ($this->getWorkerCount() < $this->limit) {
                    // Max worker count has not been reached, so create another worker.
                    $worker = $this->factory->create();
                    if (!$worker->isRunning()) {
                        throw new WorkerException('Worker factory did not create a viable worker');
                    }
                    $this->workers->attach($worker, 0);
                    return $worker;
                }

                if (!$this->waiting) {
                    $this->waiting = new DeferredFuture;
                }

                do {
                    $worker = $this->waiting->getFuture()->await();
                } while ($this->waiting);
            } else {
                // Shift a worker off the idle queue.
                $worker = $this->idleWorkers->shift();
            }

            \assert($worker instanceof Worker);

            if ($worker->isRunning()) {
                return $worker;
            }

            // Worker crashed; trigger error and remove it from the pool.

            EventLoop::queue(static function () use ($worker): void {
                try {
                    $code = $worker->shutdown();
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

        // Required for Psalm.
        throw new \RuntimeException('Unreachable statement');
    }
}
