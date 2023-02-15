<?php declare(strict_types=1);

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
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
final class ContextWorkerPool implements WorkerPool
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var bool Indicates if the pool is currently running. */
    private bool $running = true;

    /** @var \SplObjectStorage<Worker, int> A collection of all workers in the pool. */
    private readonly \SplObjectStorage $workers;

    /** @var \SplQueue<Worker> A collection of idle workers. */
    private readonly \SplQueue $idleWorkers;

    /** @var \SplQueue<DeferredFuture<Worker|null>> Task submissions awaiting an available worker. */
    private \SplQueue $waiting;

    /** @var \Closure(Worker):void */
    private readonly \Closure $push;

    private ?Future $exitStatus = null;

    private readonly DeferredCancellation $deferredCancellation;

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
    public function __construct(
        private readonly int $limit = self::DEFAULT_WORKER_LIMIT,
        private readonly ?WorkerFactory $factory = null,
    ) {
        if ($limit < 0) {
            throw new \Error("Maximum size must be a non-negative integer");
        }

        $this->workers = new \SplObjectStorage();
        $this->idleWorkers = new \SplQueue();
        $this->waiting = new \SplQueue();

        $this->deferredCancellation = new DeferredCancellation();

        $workers = $this->workers;
        $idleWorkers = $this->idleWorkers;
        $waiting = $this->waiting;

        $this->push = static function (Worker $worker) use ($waiting, $workers, $idleWorkers): void {
            if (!$workers->contains($worker)) {
                return;
            }

            if ($worker->isRunning()) {
                $idleWorkers->push($worker);
            } else {
                $workers->detach($worker);
                $worker = null;
            }

            if (!$waiting->isEmpty()) {
                $waiting->dequeue()->complete($worker);
            }
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

    /**
     * Gets the maximum number of workers the pool may spawn to handle concurrent tasks.
     *
     * @return int The maximum number of workers.
     */
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
            $execution = $worker->submit($task, $cancellation);
        } catch (\Throwable $exception) {
            $push($worker);
            throw $exception;
        }

        $execution->getFuture()->finally(static fn () => $push($worker))->ignore();

        return $execution;
    }

    /**
     * Shuts down the pool and all workers in it.
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

        while (!$this->waiting->isEmpty()) {
            $this->waiting->dequeue()->error(
                $exception ??= new WorkerException('The pool shut down before the task could be executed'),
            );
        }

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
    }

    /**
     * @param \SplObjectStorage<Worker, int> $workers
     * @param \SplQueue<DeferredFuture<Worker|null>> $waiting
     */
    private static function killWorkers(\SplObjectStorage $workers, \SplQueue $waiting): void
    {
        foreach ($workers as $worker) {
            \assert($worker instanceof Worker);
            if ($worker->isRunning()) {
                $worker->kill();
            }
        }

        while (!$waiting->isEmpty()) {
            $waiting->dequeue()->error(
                $exception ??= new WorkerException('The pool was killed before the task could be executed'),
            );
        }
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
                    try {
                        // Max worker count has not been reached, so create another worker.
                        $worker = ($this->factory ?? workerFactory())->create(
                            $this->deferredCancellation->getCancellation(),
                        );
                    } catch (CancelledException) {
                        throw new WorkerException('The pool shut down before the task could be executed');
                    }

                    if (!$worker->isRunning()) {
                        throw new WorkerException('Worker factory did not create a viable worker');
                    }

                    $this->workers->attach($worker, 0);
                    return $worker;
                }

                /** @var DeferredFuture<Worker|null> $deferred */
                $deferred = new DeferredFuture;
                $this->waiting->enqueue($deferred);

                $worker = $deferred->getFuture()->await();
            } else {
                // Shift a worker off the idle queue.
                $worker = $this->idleWorkers->shift();
            }

            if ($worker === null) {
                // Worker crashed when executing a Task, which should have failed.
                continue;
            }

            \assert($worker instanceof Worker);

            if ($worker->isRunning()) {
                return $worker;
            }

            // Worker crashed while idle; trigger error and remove it from the pool.
            EventLoop::queue(static function () use ($worker): void {
                try {
                    $worker->shutdown();
                    \trigger_error('Worker in pool exited unexpectedly', \E_USER_WARNING);
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
