<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\ChannelException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

/**
 * Base class for most common types of task workers.
 */
abstract class AbstractWorker implements Worker
{
    /** @var \Amp\Parallel\Context\Context */
    private $context;

    /** @var bool */
    private $shutdown = false;

    /** @var \Amp\Promise|null */
    private $pending;

    /**
     * @param \Amp\Parallel\Context\Context $context A context running an instance of TaskRunner.
     */
    public function __construct(Context $context)
    {
        if ($context->isRunning()) {
            throw new \Error("The context was already running");
        }

        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return !$this->shutdown;
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle(): bool
    {
        return $this->pending === null;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task): Promise
    {
        if ($this->shutdown) {
            throw new StatusError("The worker has been shut down");
        }

        $promise = $this->pending = call(function () use ($task) {
            if ($this->pending) {
                try {
                    yield $this->pending;
                } catch (\Throwable $exception) {
                    // Ignore error from prior job.
                }
            }

            if ($this->shutdown) {
                throw new WorkerException("The worker was shutdown");
            }

            if (!$this->context->isRunning()) {
                yield $this->context->start();
            }

            $job = new Internal\Job($task);

            try {
                yield $this->context->send($job);
                $result = yield $this->context->receive();
            } catch (ChannelException $exception) {
                throw new WorkerException("Communicating with the worker failed", 0, $exception);
            }

            if (!$result instanceof Internal\TaskResult) {
                $this->kill();
                throw new WorkerException("Context did not return a task result");
            }

            if ($result->getId() !== $job->getId()) {
                $this->kill();
                throw new WorkerException("Task results returned out of order");
            }

            return $result->promise();
        });

        $promise->onResolve(function () use ($promise) {
            if ($this->pending === $promise) {
                $this->pending = null;
            }
        });

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): Promise
    {
        if ($this->shutdown) {
            return new Success(0);
        }

        $this->shutdown = true;

        if (!$this->context->isRunning()) {
            return new Success(0);
        }

        return call(function () {
            if ($this->pending) {
                // If a task is currently running, wait for it to finish.
                yield Promise\any([$this->pending]);
            }

            yield $this->context->send(0);
            return yield $this->context->join();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function kill()
    {
        $this->shutdown = true;

        if ($this->context->isRunning()) {
            $this->context->kill();
        }
    }
}
