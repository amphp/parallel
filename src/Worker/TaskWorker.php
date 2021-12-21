<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Worker\Internal\JobCancellation;
use Amp\Pipeline\Emitter;
use Amp\TimeoutCancellation;
use function Amp\async;

/**
 * Base class for workers executing {@see Task}s.
 */
abstract class TaskWorker implements Worker
{
    private const SHUTDOWN_TIMEOUT = 1;
    private const ERROR_TIMEOUT = 0.25;

    private Context $context;

    private ?Future $receiveFuture;

    /** @var array<string, DeferredFuture> */
    private array $jobQueue = [];

    /** @var array<string, Emitter> */
    private array $emitters = [];

    private \Closure $onReceive;

    private ?Future $exitStatus = null;

    /**
     * @param Context $context A context running an instance of {@see TaskRunner}.
     */
    public function __construct(Context $context)
    {
        $this->context = $context;

        $jobQueue = &$this->jobQueue;
        $emitters = &$this->emitters;
        $receiveFuture = &$this->receiveFuture;
        $this->onReceive = $onReceive = static function (
            ?\Throwable $exception,
            Internal\TaskResult|Internal\JobMessage|null $data
        ) use (
            $context,
            &$jobQueue,
            &$emitters,
            &$receiveFuture,
            &$onReceive,
        ): void {
            $receiveFuture = null;

            if (!$data) {
                $exception ??= new WorkerException("Unexpected error in worker");
                foreach ($emitters as $emitter) {
                    $emitter->error($exception);
                }
                foreach ($jobQueue as $deferred) {
                    $deferred->error($exception);
                }
                $context->kill();
                return;
            }

            $id = $data->getId();

            try {
                if (!isset($jobQueue[$id], $emitters[$id])) {
                    return;
                }

                if ($data instanceof Internal\JobMessage) {
                    $emitters[$id]->emit($data->getMessage())->ignore();
                    return;
                }

                $deferred = $jobQueue[$id];
                $emitter = $emitters[$id];
                unset($jobQueue[$id], $emitters[$id]);

                $emitter->complete();

                try {
                    $deferred->complete($data->getResult());
                } catch (\Throwable $exception) {
                    $deferred->error($exception);
                }
            } finally {
                if (!empty($jobQueue)) {
                    $receiveFuture = self::receive($context, $onReceive);
                }
            }
        };
    }

    private static function receive(Context $context, callable $onReceive): Future
    {
        return async(static function () use ($context, $onReceive): void {
            try {
                $received = $context->receive();
            } catch (\Throwable $exception) {
                $onReceive($exception, null);
                return;
            }

            $onReceive(null, $received);
        });
    }

    final public function isRunning(): bool
    {
        // Report as running unless shutdown or killed.
        return $this->exitStatus === null;
    }

    final public function isIdle(): bool
    {
        return empty($this->jobQueue);
    }

    final public function enqueue(Task $task, ?Cancellation $cancellation = null): Job
    {
        if ($this->exitStatus !== null) {
            throw new StatusError("The worker has been shut down");
        }

        $activity = new Internal\Activity($task);
        $jobId = $activity->getId();
        $deferred = new DeferredFuture;
        $future = $deferred->getFuture();

        try {
            $this->context->send($activity);

            if ($cancellation) {
                $cancellationId = $cancellation->subscribe(
                    fn () => async(fn () => $this->context->send(new JobCancellation($jobId)))->ignore()
                );
                $future->finally(fn () => $cancellation->unsubscribe($cancellationId))->ignore();
            }
        } catch (ChannelException $exception) {
            try {
                $exception = new WorkerException("The worker exited unexpectedly", 0, $exception);
                async(fn () => $this->context->join())
                    ->await(new TimeoutCancellation(self::ERROR_TIMEOUT));
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

        $this->jobQueue[$jobId] = $deferred;
        $this->emitters[$jobId] = $emitter = new Emitter();
        $channel = new Internal\JobChannel($jobId, $this->context, $emitter->pipe());

        if ($this->receiveFuture === null) {
            $this->receiveFuture = self::receive($this->context, $this->onReceive);
        }

        return new Job($task, $channel, $future);
    }

    final public function shutdown(): int
    {
        if ($this->exitStatus !== null) {
            return $this->exitStatus->await();
        }

        return ($this->exitStatus = async(function (): int {
            if (!$this->context->isRunning()) {
                throw new WorkerException("The worker had crashed prior to being shutdown");
            }

            // Wait for pending tasks to finish.
            Future\settle(\array_map(fn (DeferredFuture $deferred) => $deferred->getFuture(), $this->jobQueue));

            $this->context->send('');

            try {
                return async(fn () => $this->context->join())
                    ->await(new TimeoutCancellation(self::SHUTDOWN_TIMEOUT));
            } catch (\Throwable $exception) {
                $this->context->kill();
                throw new WorkerException("Failed to gracefully shutdown worker", 0, $exception);
            }
        }))->await();
    }

    final public function kill(): void
    {
        if ($this->exitStatus !== null) {
            return;
        }

        if ($this->context->isRunning()) {
            $this->context->kill();
            $this->exitStatus = Future::error(new WorkerException("The worker was killed"));
            $this->exitStatus->ignore();
            return;
        }

        $this->exitStatus = Future::complete();
    }
}
