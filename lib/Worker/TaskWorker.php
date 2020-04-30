<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Failure;
use Amp\NullCancellationToken;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\ChannelException;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use function Amp\call;

/**
 * Base class for workers executing {@see Task}s.
 */
abstract class TaskWorker implements Worker
{
    const SHUTDOWN_TIMEOUT = 1000;
    const ERROR_TIMEOUT = 250;

    /** @var Context */
    private $context;

    /** @var Promise */
    private $startPromise;

    /** @var Promise|null */
    private $receivePromise;

    /** @var Deferred[] */
    private $jobQueue = [];

    /** @var \Closure */
    private $onResolve;

    /** @var Promise|null */
    private $exitStatus;

    /**
     * @param Context $context A context running an instance of {@see TaskRunner}.
     */
    public function __construct(Context $context)
    {
        if ($context->isRunning()) {
            throw new \Error("The context was already running");
        }

        $this->context = $context;
        $this->startPromise = $this->context->start();

        $jobQueue = &$this->jobQueue;
        $receive = &$this->receivePromise;
        $this->onResolve = $onResolve = static function (?\Throwable $exception, $data) use (
            $context, &$jobQueue, &$receive, &$onResolve
        ): void {
            $receive = null;

            if ($exception || !$data instanceof Internal\TaskResult) {
                $exception = new WorkerException("Invalid data from worker", 0, $exception);
                foreach ($jobQueue as $deferred) {
                    $deferred->fail($exception);
                }
                $context->kill();
                return;
            }

            $id = $data->getId();

            try {
                if (!isset($jobQueue[$id])) {
                    return;
                }

                $deferred = $jobQueue[$id];
                unset($jobQueue[$id]);

                $deferred->resolve($data->promise());
            } finally {
                if ($receive === null && !empty($jobQueue)) {
                    $receive = $context->receive();
                    $receive->onResolve($onResolve);
                }
            }
        };

        $context = &$this->context;
        \register_shutdown_function(static function () use (&$context, &$jobQueue): void {
            if ($context === null || !$context->isRunning()) {
                return;
            }

            try {
                Promise\wait(Promise\timeout(call(function () use ($context, $jobQueue): \Generator {
                    // Wait for pending tasks to finish.
                    yield Promise\any(\array_map(function (Deferred $deferred): Promise {
                        return $deferred->promise();
                    }, $jobQueue));

                    yield $context->send(null);
                    return yield $context->join();
                }), self::SHUTDOWN_TIMEOUT));
            } catch (\Throwable $exception) {
                if ($context !== null) {
                    $context->kill();
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        // Report as running unless shutdown or crashed.
        return $this->exitStatus === null && $this->context !== null && $this->context->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle(): bool
    {
        return empty($this->jobQueue);
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task, ?CancellationToken $token = null): Promise
    {
        if ($this->exitStatus !== null || $this->context === null) {
            throw new StatusError("The worker has been shut down");
        }

        $token = $token ?? new NullCancellationToken;

        return call(function () use ($task, $token): \Generator {
            $job = new Internal\Job($task);
            $jobId = $job->getId();
            $this->jobQueue[$jobId] = $deferred = new Deferred;

            yield $this->startPromise;

            try {
                yield $this->context->send($job);
            } catch (ChannelException $exception) {
                unset($this->jobQueue[$jobId]);

                try {
                    yield Promise\timeout($this->context->join(), self::ERROR_TIMEOUT);
                } catch (TimeoutException $timeout) {
                    $this->kill();
                    throw new WorkerException("The worker failed unexpectedly", 0, $exception);
                }

                throw new WorkerException("The worker exited unexpectedly", 0, $exception);
            } catch (\Throwable $exception) {
                unset($this->jobQueue[$jobId]);
                throw $exception;
            }

            $promise = $deferred->promise();

            if ($this->context !== null) {
                $context = $this->context;
                $cancellationId = $token->subscribe(static function () use ($jobId, $context): void {
                    $context->send($jobId);
                });
                $promise->onResolve(static function () use ($token, $cancellationId): void {
                    $token->unsubscribe($cancellationId);
                });
            }

            if ($this->receivePromise === null) {
                $this->receivePromise = $this->context->receive();
                $this->receivePromise->onResolve($this->onResolve);
            }

            return $promise;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): Promise
    {
        if ($this->exitStatus !== null) {
            return $this->exitStatus;
        }

        if ($this->context === null || !$this->context->isRunning()) {
            return $this->exitStatus = new Failure(new WorkerException("The worker had crashed prior to being shutdown"));
        }

        return $this->exitStatus = call(function (): \Generator {
            yield $this->startPromise; // Ensure the context has fully started before sending shutdown signal.

            // Wait for pending tasks to finish.
            yield Promise\any(\array_map(function (Deferred $deferred): Promise {
                return $deferred->promise();
            }, $this->jobQueue));

            yield $this->context->send(null);

            try {
                return yield Promise\timeout($this->context->join(), self::SHUTDOWN_TIMEOUT);
            } catch (\Throwable $exception) {
                $this->context->kill();
                throw new WorkerException("Failed to gracefully shutdown worker", 0, $exception);
            } finally {
                // Null properties to free memory because the shutdown function has references to these.
                $this->context = null;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function kill(): void
    {
        if ($this->exitStatus !== null || $this->context === null) {
            return;
        }

        if ($this->context->isRunning()) {
            $this->context->kill();
            $this->exitStatus = new Failure(new WorkerException("The worker was killed"));
            return;
        }

        $this->exitStatus = new Success;

        // Null properties to free memory because the shutdown function has references to these.
        $this->context = null;
    }
}
