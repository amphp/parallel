<?php
namespace Icicle\Concurrent\Worker\Internal;

use Icicle\Concurrent\Worker\Task;
use Icicle\Concurrent\Worker\Worker;

class PooledWorker implements Worker
{
    /**
     * @var callable
     */
    private $push;

    /**
     * @var \Icicle\Concurrent\Worker\Worker
     */
    private $worker;

    /**
     * @param \Icicle\Concurrent\Worker\Worker $worker
     * @param callable $push Callable to push the worker back into the queue.
     */
    public function __construct(Worker $worker, callable $push)
    {
        $this->worker = $worker;
        $this->push = $push;
    }

    /**
     * Automatically pushes the worker back into the queue.
     */
    public function __destruct()
    {
        $push = $this->push;
        $push($this->worker);
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->worker->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle()
    {
        return $this->worker->isIdle();
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->worker->start();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task)
    {
        return $this->worker->enqueue($task);
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        return $this->worker->shutdown();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->worker->kill();
    }
}