<?php

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\ChannelException;
use Amp\Pipeline\Emitter;
use Amp\TimeoutCancellation;
use Revolt\EventLoop;
use function Amp\async;

/**
 * Base class for workers executing {@see Task}s.
 */
final class TaskWorker implements Worker
{
    private const SHUTDOWN_TIMEOUT = 1;
    private const ERROR_TIMEOUT = 0.25;

    /** @var array<string, DeferredFuture> */
    private array $jobQueue = [];

    /** @var array<string, Emitter> */
    private array $emitters = [];

    private \Closure $onReceive;

    private ?Future $exitStatus = null;

    /**
     * @param Context<int, Internal\JobPacket, Internal\JobPacket|int> $context A context running an instance of
     * {@see TaskRunner}.
     */
    public function __construct(private Context $context)
    {
        $jobQueue = &$this->jobQueue;
        $emitters = &$this->emitters;
        $this->onReceive = $onReceive = static function (
            ?\Throwable $exception,
            ?Internal\JobPacket $data
        ) use (
            $context,
            &$jobQueue,
            &$emitters,
            &$onReceive,
        ): void {
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

                if (!$data instanceof Internal\TaskResult) {
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
                    self::receive($context, $onReceive);
                }
            }
        };
    }

    private static function receive(Context $context, callable $onReceive): void
    {
        EventLoop::queue(static function () use ($context, $onReceive): void {
            try {
                $received = $context->receive();
            } catch (\Throwable $exception) {
                $onReceive($exception, null);
                return;
            }

            $onReceive(null, $received);
        });
    }

    public function isRunning(): bool
    {
        // Report as running unless shutdown or killed.
        return $this->exitStatus === null;
    }

    public function isIdle(): bool
    {
        return empty($this->jobQueue);
    }

    public function enqueue(Task $task, ?Cancellation $cancellation = null): Job
    {
        if ($this->exitStatus !== null) {
            throw new StatusError("The worker has been shut down");
        }

        $receive = empty($this->jobQueue);
        $activity = new Internal\TaskEnqueue($task);
        $jobId = $activity->getId();
        $this->jobQueue[$jobId] = $deferred = new DeferredFuture;
        $future = $deferred->getFuture();

        try {
            $this->context->send($activity);

            if ($cancellation) {
                $context = $this->context;
                $cancellationId = $cancellation->subscribe(
                    static fn () => async(static fn () => $context->send(
                        new Internal\JobCancellation($jobId),
                    ))->ignore()
                );
                $future = $future->finally(static fn () => $cancellation->unsubscribe($cancellationId));
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

            unset($this->jobQueue[$jobId]);
            throw $exception;
        } catch (\Throwable $exception) {
            unset($this->jobQueue[$jobId]);
            throw $exception;
        }

        $this->emitters[$jobId] = $emitter = new Emitter();
        $channel = new Internal\JobChannel($jobId, $this->context, $emitter->pipe());

        if ($receive) {
            self::receive($this->context, $this->onReceive);
        }

        return new Job($task, $channel, $future);
    }

    public function shutdown(): int
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

            $this->context->send(0);

            try {
                return async(fn () => $this->context->join())
                    ->await(new TimeoutCancellation(self::SHUTDOWN_TIMEOUT));
            } catch (\Throwable $exception) {
                $this->context->kill();
                throw new WorkerException("Failed to gracefully shutdown worker", 0, $exception);
            }
        }))->await();
    }

    public function kill(): void
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
