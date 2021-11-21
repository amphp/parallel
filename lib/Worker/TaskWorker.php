<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Deferred;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\ChannelException;
use Amp\Serialization\SerializationException;
use Amp\TimeoutCancellationToken;
use function Amp\launch;

/**
 * Base class for workers executing {@see Task}s.
 */
abstract class TaskWorker implements Worker
{
    private const SHUTDOWN_TIMEOUT = 1;
    private const ERROR_TIMEOUT = 0.25;

    private ?Context $context;

    private ?Future $receiveFuture;

    /** @var Deferred[] */
    private array $jobQueue = [];

    private \Closure $onReceive;

    private ?Future $exitStatus = null;

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
        $receive = &$this->receiveFuture;
        $this->onReceive = $onReceive = static function (?\Throwable $exception, mixed $data) use (
            $context,
            &$jobQueue,
            &$receive,
            &$onReceive
        ): void {
            $receive = null;

            if ($exception || !$data instanceof Internal\TaskResult) {
                $exception ??= new WorkerException("Invalid data from worker");
                foreach ($jobQueue as $deferred) {
                    $deferred->error($exception);
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

                try {
                    $deferred->complete($data->getResult());
                } catch (\Throwable $exception) {
                    $deferred->error($exception);
                }
            } finally {
                if ($receive === null && !empty($jobQueue)) {
                    $receive = self::receive($context, $onReceive);
                }
            }
        };
    }

    private static function receive(Context $context, callable $onReceive): Future
    {
        return launch(static function () use ($context, $onReceive): void {
            try {
                $received = $context->receive();
            } catch (\Throwable $exception) {
                $onReceive($exception, null);
                return;
            }

            $onReceive(null, $received);
        });
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

        $job = new Internal\Job($task);
        $jobId = $job->getId();
        $this->jobQueue[$jobId] = $deferred = new Deferred;
        $future = $deferred->getFuture();

        try {
            $this->context->send($job)->await();

            if ($token) {
                $context = $this->context;
                $cancellationId = $token->subscribe(static fn () => $context->send($jobId)->ignore());
                $future->finally(fn () => $token->unsubscribe($cancellationId))->ignore();
            }
        } catch (SerializationException $exception) {
            // Could not serialize Task object.
            unset($this->jobQueue[$jobId]);
            throw $exception;
        } catch (ChannelException $exception) {
            unset($this->jobQueue[$jobId]);

            try {
                $exception = new WorkerException("The worker exited unexpectedly", 0, $exception);
                launch(fn () => $this->context->join())
                    ->await(new TimeoutCancellationToken(self::ERROR_TIMEOUT));
            } catch (CancelledException) {
                $this->kill();
            } catch (\Throwable $exception) {
                $exception = new WorkerException("The worker crashed", 0, $exception);
            }

            if ($this->exitStatus === null) {
                $this->exitStatus = Future::error($exception);
            }

            throw $exception;
        }

        if ($this->receiveFuture === null) {
            $this->receiveFuture = self::receive($this->context, $this->onReceive);
        }

        return $future->await();
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): int
    {
        if ($this->exitStatus !== null) {
            return $this->exitStatus->await();
        }

        return ($this->exitStatus = launch(function (): int {
            if (!$this->context->isRunning()) {
                throw new WorkerException("The worker had crashed prior to being shutdown");
            }

            // Wait for pending tasks to finish.
            Future\settle(\array_map(fn (Deferred $deferred) => $deferred->getFuture(), $this->jobQueue));

            $this->context->send(null)->await();

            try {
                return launch(fn () => $this->context->join())
                    ->await(new TimeoutCancellationToken(self::SHUTDOWN_TIMEOUT));
            } catch (\Throwable $exception) {
                $this->context->kill();
                throw new WorkerException("Failed to gracefully shutdown worker", 0, $exception);
            } finally {
                // Null properties to free memory because the shutdown function has references to these.
                $this->context = null;
            }
        }))->await();
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
            $this->exitStatus = Future::error(new WorkerException("The worker was killed"));
            $this->exitStatus->ignore();
            return;
        }

        $this->exitStatus = Future::complete(null);

        // Null properties to free memory because the shutdown function has references to these.
        $this->context = null;
    }
}
