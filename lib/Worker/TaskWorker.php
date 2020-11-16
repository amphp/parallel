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
use Amp\Serialization\SerializationException;
use Amp\Success;
use Amp\TimeoutException;
use function Amp\async;
use function Amp\await;

/**
 * Base class for workers executing {@see Task}s.
 */
abstract class TaskWorker implements Worker
{
    private const SHUTDOWN_TIMEOUT = 1000;
    private const ERROR_TIMEOUT = 250;

    private ?Context $context;

    private ?Promise $receivePromise;

    /** @var Deferred[] */
    private array $jobQueue = [];

    private \Closure $onResolve;

    private ?Promise $exitStatus = null;

    /**
     * @param Context $context A context running an instance of {@see TaskRunner}.
     */
    public function __construct(Context $context)
    {
        if ($context->isRunning()) {
            throw new \Error("The context was already running");
        }

        $this->context = $context;
        $this->context->start();

        $jobQueue = &$this->jobQueue;
        $receive = &$this->receivePromise;
        $this->onResolve = $onResolve = static function (?\Throwable $exception, mixed $data) use (
            $context, &$jobQueue, &$receive, &$onResolve
        ): void {
            $receive = null;

            if ($exception || !$data instanceof Internal\TaskResult) {
                $exception = $exception ?? new WorkerException("Invalid data from worker");
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
                    $receive = async(fn() => $context->receive());
                    $receive->onResolve($onResolve);
                }
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        // Report as running unless shutdown or killed.
        return $this->exitStatus === null;
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
    public function enqueue(Task $task, ?CancellationToken $token = null): mixed
    {
        if ($this->exitStatus !== null || $this->context === null) {
            throw new StatusError("The worker has been shut down");
        }

        $token = $token ?? new NullCancellationToken;

        $job = new Internal\Job($task);
        $jobId = $job->getId();
        $this->jobQueue[$jobId] = $deferred = new Deferred;

        try {
            $this->context->send($job);
        } catch (SerializationException $exception) {
            // Could not serialize Task object.
            unset($this->jobQueue[$jobId]);
            throw $exception;
        } catch (ChannelException $exception) {
            unset($this->jobQueue[$jobId]);

            try {
                $exception = new WorkerException("The worker exited unexpectedly", 0, $exception);
                await(Promise\timeout(async(fn() => $this->context->join()), self::ERROR_TIMEOUT));
            } catch (TimeoutException $timeout) {
                $this->kill();
            } catch (\Throwable $exception) {
                $exception = new WorkerException("The worker crashed", 0, $exception);
            }

            if ($this->exitStatus === null) {
                $this->exitStatus = new Failure($exception);
            }

            throw $exception;
        }

        $promise = $deferred->promise();

        if ($this->context !== null) {
            $context = $this->context;
            $cancellationId = $token->subscribe(static function () use ($jobId, $context): void {
                try {
                    $context->send($jobId);
                } catch (\Throwable $exception) {
                    return;
                }
            });
            $promise->onResolve(static fn() => $token->unsubscribe($cancellationId));
        }

        if ($this->receivePromise === null) {
            $this->receivePromise = async(fn () => $this->context->receive());
            $this->receivePromise->onResolve($this->onResolve);
        }

        return await($promise);
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): int
    {
        if ($this->exitStatus !== null) {
            return await($this->exitStatus);
        }

        return await($this->exitStatus = async(function (): int {
            if (!$this->context->isRunning()) {
                throw new WorkerException("The worker had crashed prior to being shutdown");
            }

            // Wait for pending tasks to finish.
            await(Promise\any(\array_map(function (Deferred $deferred): Promise {
                return $deferred->promise();
            }, $this->jobQueue)));

            $this->context->send(null);

            try {
                return await(Promise\timeout(async(fn() => $this->context->join()), self::SHUTDOWN_TIMEOUT));
            } catch (\Throwable $exception) {
                $this->context->kill();
                throw new WorkerException("Failed to gracefully shutdown worker", 0, $exception);
            } finally {
                // Null properties to free memory because the shutdown function has references to these.
                $this->context = null;
            }
        }));
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
