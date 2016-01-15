<?php
namespace Icicle\Concurrent\Worker;

/**
 * An interface for worker pools.
 */
interface Pool extends Worker
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
     * Gets a worker from the pool. The worker is marked as busy and will only be reused if the pool runs out of
     * idle workers. The worker will be automatically marked as idle once no references to the returned worker remain.
     *
     * @return \Icicle\Concurrent\Worker\Worker
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the queue is not running.
     */
    public function get();

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

    /**
     * Gets the minimum number of workers the pool may have idle.
     *
     * @return int The minimum number of workers.
     */
    public function getMinSize();

    /**
     * Gets the maximum number of workers the pool may spawn to handle concurrent tasks.
     *
     * @return int The maximum number of workers.
     */
    public function getMaxSize();
}
