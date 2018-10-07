<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Worker\Internal\TaskResult;
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
     * @param \Amp\Parallel\Context\Context $context
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
        return $this->context->isRunning();
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

        $promise = $this->pending = call(function () use (&$promise, $task) {
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

            yield $this->context->send($job);
            $result = yield $this->context->receive();

            if (!$result instanceof TaskResult) {
                $this->cancel(new WorkerException("Context did not return a task result"));
            }

            if ($result->getId() !== $job->getId()) {
                $this->cancel(new WorkerException("Task results returned out of order"));
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
            throw new StatusError("The worker is not running");
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
        $this->cancel();
    }

    /**
     * Cancels all pending tasks and kills the context.
     *
     * @TODO Parameter kept for BC, remove in future version.
     *
     * @param \Throwable|null $exception Optional exception to be used as the previous exception.
     */
    protected function cancel(\Throwable $exception = null)
    {
        if ($this->context->isRunning()) {
            $this->context->kill();
        }
    }
}
