<?php
namespace Icicle\Concurrent\Worker;

interface Queue
{
    /**
     * @var int The default minimum queue size.
     */
    const DEFAULT_MIN_SIZE = 4;

    /**
     * @var int The default maximum queue size.
     */
    const DEFAULT_MAX_SIZE = 32;

    /**
     * Pull a worker from the queue. The worker is marked as busy and will only be reused if the queue runs out of
     * idle workers.
     *
     * @return \Icicle\Concurrent\Worker\Worker
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the queue is not running.
     */
    public function pull();

    /**
     * Pushes a worker into the queue, marking it as idle and available to be pulled from the queue again.
     *
     * @param \Icicle\Concurrent\Worker\Worker $worker
     *
     * @throws \Icicle\Concurrent\Exception\StatusError If the queue is not running.
     * @throws \Icicle\Exception\InvalidArgumentError If the given worker is not part of this queue or was already
     *     pushed into the queue.
     */
    public function push(Worker $worker);

    /**
     * Checks if the queue is running.
     *
     * @return bool True if the queue is running, otherwise false.
     */
    public function isRunning();

    /**
     * Starts the worker queue execution.
     *
     * When the worker queue starts up, the minimum number of workers will be created. This adds some overhead to
     * starting the queue, but allows for greater performance during runtime.
     */
    public function start();

    /**
     * Gracefully shuts down all workers in the queue.
     *
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve int Exit code.
     */
    public function shutdown();

    /**
     * Immediately kills all workers in the queue.
     */
    public function kill();

    /**
     * Gets the minimum number of workers the queue may have idle.
     *
     * @return int The minimum number of workers.
     */
    public function getMinSize();

    /**
     * Gets the maximum number of workers the queue may spawn to handle concurrent tasks.
     *
     * @return int The maximum number of workers.
     */
    public function getMaxSize();

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