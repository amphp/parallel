<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Concurrent\Exception\InvalidArgumentError;

/**
 * Provides a pool of workers that can be used to execute multiple tasks asynchronously.
 *
 * A worker pool is a collection of worker threads that can perform multiple
 * tasks simultaneously. The load on each worker is balanced such that tasks
 * are completed as soon as possible and workers are used efficiently.
 */
class WorkerPool
{
    /**
     * @var int The minimum number of workers the pool should spawn.
     */
    private $minSize;

    /**
     * @var int The maximum number of workers the pool should spawn.
     */
    private $maxSize;

    /**
     * Creates a new worker pool.
     *
     * @param int $minSize The minimum number of workers the pool should spawn.
     * @param int $maxSize The maximum number of workers the pool should spawn.
     */
    public function __construct(WorkerFactory $factory, $minSize, $maxSize = null)
    {
        if (!is_int($minSize) || $minSize < 0) {
            throw new InvalidArgumentError('Minimum size must be a non-negative integer.');
        }
        $this->minSize = $minSize;

        if ($maxSize === null) {
            $this->maxSize = $minSize;
        } elseif (!is_int($maxSize) || $maxSize < 0) {
            throw new InvalidArgumentError('Maximum size must be a non-negative integer.');
        } else {
            $this->maxSize = $maxSize;
        }
    }

    public function getMinSize()
    {
        return $this->minSize;
    }

    public function getMaxSize()
    {
        return $this->maxSize;
    }

    /**
     * Gets the number of workers that have been spawned.
     *
     * @return int
     */
    public function getWorkerCount()
    {
    }

    /**
     * Gets the number of workers that are currently idle.
     *
     * @return int
     */
    public function getIdleWorkerCount()
    {
    }

    /**
     * Enqueues a task to be executed in the worker pool.
     *
     * @param TaskInterface $task The task to execute.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function enqueue(TaskInterface $task)
    {
    }
}
