<?php

namespace Amp\Parallel\Worker;

use Amp\Deferred;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\SerializationException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

/**
 * Base class for most common types of task workers.
 */
abstract class AbstractWorker implements Worker {
    /** @var \Amp\Parallel\Context\Context */
    private $context;

    /** @var bool */
    private $shutdown = false;

    /** @var \Amp\Deferred[] */
    private $jobQueue = [];

    /** @var callable */
    private $onResolve;

    /**
     * @param \Amp\Parallel\Context\Context $context
     */
    public function __construct(Context $context) {
        if ($context->isRunning()) {
            throw new \Error("The context was already running");
        }

        $this->context = $context;

        $this->onResolve = function ($exception, $data) {
            if ($exception) {
                $this->cancel($exception);
                return;
            }

            if (!$data instanceof Internal\TaskResult) {
                $this->cancel(new ContextException("Context did not return a task result"));
                return;
            }

            $id = $data->getId();

            if (!isset($this->jobQueue[$id])) {
                $this->cancel(new ContextException("Job ID returned by context does not exist"));
                return;
            }

            $deferred = $this->jobQueue[$id];
            unset($this->jobQueue[$id]);
            $empty = empty($this->jobQueue);

            $deferred->resolve($data->promise());

            if (!$empty) {
                $this->context->receive()->onResolve($this->onResolve);
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool {
        return $this->context->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle(): bool {
        return empty($this->jobQueue);
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task): Promise {
        if ($this->shutdown) {
            throw new StatusError("The worker has been shut down");
        }

        if (!$this->context->isRunning()) {
            $this->context->start();
        }

        return call(function () use ($task) {
            $empty = empty($this->jobQueue);

            $job = new Internal\Job($task);
            $this->jobQueue[$job->getId()] = $deferred = new Deferred;

            try {
                yield $this->context->send($job);
                if ($empty) {
                    $this->context->receive()->onResolve($this->onResolve);
                }
            } catch (SerializationException $exception) {
                unset($this->jobQueue[$job->getId()]);
                $deferred->fail($exception);
            } catch (\Throwable $exception) {
                $this->cancel($exception);
            }

            return $deferred->promise();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): Promise {
        if ($this->shutdown) {
            throw new StatusError("The worker is not running");
        }

        $this->shutdown = true;

        if (!$this->context->isRunning()) {
            return new Success(0);
        }

        return call(function () {
            if (!empty($this->jobQueue)) {
                // If a task is currently running, wait for it to finish.
                yield Promise\any(\array_map(function (Deferred $deferred): Promise {
                    return $deferred->promise();
                }, $this->jobQueue));
            }

            yield $this->context->send(0);
            return yield $this->context->join();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function kill() {
        $this->cancel();
    }

    /**
     * Cancels all pending tasks and kills the context.
     *
     * @param \Throwable|null $exception Optional exception to be used as the previous exception.
     */
    protected function cancel(\Throwable $exception = null) {
        if (!empty($this->jobQueue)) {
            $exception = new WorkerException('Worker was shut down', $exception);

            foreach ($this->jobQueue as $job) {
                $job->fail($exception);
            }

            $this->jobQueue = [];
        }

        if ($this->context->isRunning()) {
            $this->context->kill();
        }
    }
}
