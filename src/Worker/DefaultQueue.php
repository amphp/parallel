<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Awaitable;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Coroutine\Coroutine;
use Icicle\Exception\InvalidArgumentError;

class DefaultQueue implements Queue
{
    /**
     * @var \Icicle\Concurrent\Worker\WorkerFactory
     */
    private $factory;

    /**
     * @var int The minimum number of workers the queue should spawn.
     */
    private $minSize;

    /**
     * @var int The maximum number of workers the queue should spawn.
     */
    private $maxSize;

    /**
     * @var \SplQueue
     */
    private $idle;

    /**
     * @var \SplQueue
     */
    private $busy;

    /**
     * @var \SplObjectStorage
     */
    private $workers;

    /**
     * @var bool
     */
    private $running = false;

    /**
     * @var \Closure
     */
    private $push;

    /**
     * @param int|null $minSize The minimum number of workers the queue should spawn.
     *     Defaults to `Queue::DEFAULT_MIN_SIZE`.
     * @param int|null $maxSize The maximum number of workers the queue should spawn.
     *     Defaults to `Queue::DEFAULT_MAX_SIZE`.
     * @param \Icicle\Concurrent\Worker\WorkerFactory|null $factory Factory used to create new workers.
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the min or max size are invalid.
     */
    public function __construct($minSize = null, $maxSize = null, WorkerFactory $factory = null)
    {
        $minSize = $minSize ?: self::DEFAULT_MIN_SIZE;
        $maxSize = $maxSize ?: self::DEFAULT_MAX_SIZE;

        if (!is_int($minSize) || $minSize < 0) {
            throw new InvalidArgumentError('Minimum size must be a non-negative integer.');
        }

        if (!is_int($maxSize) || $maxSize < 0 || $maxSize < $minSize) {
            throw new InvalidArgumentError('Maximum size must be a non-negative integer at least '.$minSize.'.');
        }

        $this->factory = $factory ?: factory();
        $this->minSize = $minSize;
        $this->maxSize = $maxSize;
        $this->workers = new \SplObjectStorage();
        $this->idle = new \SplQueue();
        $this->busy = new \SplQueue();

        $this->push = function (Worker $worker) {
            $this->push($worker);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->running;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->isRunning()) {
            throw new StatusError('The worker queue has already been started.');
        }

        // Start up the pool with the minimum number of workers.
        $count = $this->minSize;
        while (--$count >= 0) {
            $worker = $this->factory->create();
            $worker->start();
            $this->workers->attach($worker, 0);
            $this->idle->push($worker);
        }

        $this->running = true;
    }

    /**
     * {@inheritdoc}
     */
    public function pull()
    {
        if (!$this->isRunning()) {
            throw new StatusError('The queue is not running.');
        }

        do {
            if ($this->idle->isEmpty()) {
                if ($this->busy->count() >= $this->maxSize) {
                    // All possible workers busy, so shift from head (will be pushed back onto tail below).
                    $worker = $this->busy->shift();
                } else {
                    // Max worker count has not been reached, so create another worker.
                    $worker = $this->factory->create();
                    $worker->start();
                    $this->workers->attach($worker, 0);
                }
            } else {
                // Shift a worker off the idle queue.
                $worker = $this->idle->shift();
            }
        } while (!$worker->isRunning());

        $this->busy->push($worker);
        $this->workers[$worker] += 1;

        return new Internal\QueuedWorker($worker, $this->push);
    }

    /**
     * Pushes the worker back into the queue.
     *
     * @param \Icicle\Concurrent\Worker\Worker $worker
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the worker was not part of this queue.
     */
    private function push(Worker $worker)
    {
        if (!$this->workers->contains($worker)) {
            throw new InvalidArgumentError(
                'The provided worker was not part of this queue.'
            );
        }

        if (0 === ($this->workers[$worker] -= 1)) {
            // Worker is completely idle, remove from busy queue and add to idle queue.
            foreach ($this->busy as $key => $busy) {
                if ($busy === $worker) {
                    unset($this->busy[$key]);
                    break;
                }
            }

            $this->idle->push($worker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task)
    {
        $worker = $this->pull();
        yield $worker->enqueue($task);
    }

    /**
     * {@inheritdoc}
     */
    public function getMinSize()
    {
        return $this->minSize;
    }

    /**
     * {@inheritdoc}
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
        return $this->idle->count() + $this->busy->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleWorkerCount()
    {
        return $this->idle->count();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        if (!$this->isRunning()) {
            throw new StatusError('The queue is not running.');
        }

        $this->running = false;

        $shutdowns = [];

        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $shutdowns[] = new Coroutine($worker->shutdown());
            }
        }

        yield Awaitable\reduce($shutdowns, function ($carry, $value) {
            return $carry ?: $value;
        }, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->running = false;

        foreach ($this->workers as $worker) {
            $worker->kill();
        }
    }
}
