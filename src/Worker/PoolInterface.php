<?php
namespace Icicle\Concurrent\Worker;

/**
 * An interface for worker pools.
 */
interface PoolInterface extends WorkerInterface
{
    /**
     * Gets the number of workers currently running in the pool.
     *
     * @return int The number of workers.
     */
    public function getWorkerCount();

    /**
     * Gets the number of workers that are currently idle.
     *
     * @return int The number of idle workers.
     */
    public function getIdleWorkerCount();
}
