<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Awaitable\Delayed;
use Icicle\Concurrent\Strand;
use Icicle\Concurrent\Exception\StatusError;
use Icicle\Concurrent\Exception\WorkerException;
use Icicle\Concurrent\Worker\Internal\TaskFailure;
use Icicle\Coroutine\Coroutine;

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
    private $shutdown = false;

    /**
     * @var \Icicle\Coroutine\Coroutine
     */
    private $active;

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
        return null === $this->active;
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
        if (null !== $this->active) {
            $delayed = new Delayed();
            $this->busyQueue->enqueue($delayed);
            yield $delayed;
        }

        $this->active = new Coroutine($this->send($task));

        try {
            $result = (yield $this->active);
        } catch (\Exception $exception) {
            $this->kill();
            throw new WorkerException('Sending the task to the worker failed.', $exception);
        } finally {
            $this->active = null;
        }

        // We're no longer busy at the moment, so dequeue a waiting task.
        if (!$this->busyQueue->isEmpty()) {
            $this->busyQueue->dequeue()->resolve();
        }

        if ($result instanceof TaskFailure) {
            throw $result->getException();
        }

        yield $result;
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Concurrent\Worker\Task $task
     *
     * @return \Generator
     *
     * @resolve mixed
     */
    private function send(Task $task)
    {
        yield $this->context->send($task);
        yield $this->context->receive();
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
        if (null !== $this->active) {
            try {
                yield $this->active;
            } catch (\Exception $exception) {
                // Ignore failure in this context.
            }
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
        if (!$this->busyQueue->isEmpty()) {
            $exception = new WorkerException('Worker was shut down.');

            do {
                $this->busyQueue->dequeue()->cancel($exception);
            } while (!$this->busyQueue->isEmpty());
        }
    }
}
