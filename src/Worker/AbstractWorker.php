<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Awaitable\Delayed;
use Icicle\Concurrent\Strand;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\WorkerException;
use Icicle\Concurrent\Worker\Internal\TaskFailure;

/**
 * Base class for most common types of task workers.
 */
abstract class AbstractWorker implements Worker
{
    /**
     * @var \Icicle\Concurrent\Strand
     */
    private $context;

    /**
     * @var bool
     */
    private $idle = true;

    /**
     * @var bool
     */
    private $shutdown = false;

    /**
     * @var \Icicle\Awaitable\Delayed
     */
    private $activeDelayed;

    /**
     * @var \SplQueue
     */
    private $busyQueue;


    /**
     * @param \Icicle\Concurrent\Strand $strand
     */
    public function __construct(Strand $strand)
    {
        $this->context = $strand;
        $this->busyQueue = new \SplQueue();
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->context->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle()
    {
        return $this->idle;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->context->start();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task)
    {
        if (!$this->context->isRunning()) {
            throw new StatusError('The worker has not been started.');
        }

        if ($this->shutdown) {
            throw new StatusError('The worker has been shut down.');
        }

        // If the worker is currently busy, store the task in a busy queue.
        if (!$this->idle) {
            $delayed = new Delayed();
            $this->busyQueue->enqueue($delayed);
            yield $delayed;
        }

        $this->idle = false;
        $this->activeDelayed = new Delayed();

        try {
            yield $this->context->send($task);

            $result = (yield $this->context->receive());
        } finally {
            $this->idle = true;
            $this->activeDelayed->resolve();

            // We're no longer busy at the moment, so dequeue a waiting task.
            if (!$this->busyQueue->isEmpty()) {
                $this->busyQueue->dequeue()->resolve();
            }
        }

        if ($result instanceof TaskFailure) {
            throw $result->getException();
        }

        yield $result;
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        if (!$this->context->isRunning() || $this->shutdown) {
            throw new StatusError('The worker is not running.');
        }

        $this->shutdown = true;

        // Cancel any waiting tasks.
        $this->cancelPending();

        // If a task is currently running, wait for it to finish.
        if (!$this->idle) {
            yield $this->activeDelayed;
        }

        yield $this->context->send(0);
        yield $this->context->join();
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->cancelPending();
        $this->context->kill();
    }

    /**
     * Cancels all pending tasks.
     */
    private function cancelPending()
    {
        $exception = new WorkerException('Worker was shut down.');

        while (!$this->busyQueue->isEmpty()) {
            $this->busyQueue->dequeue()->cancel($exception);
        }
    }
}
