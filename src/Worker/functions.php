<?php declare(strict_types=1);

namespace Amp\Parallel\Worker;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\Parallel\Worker\Internal\JobChannel;
use Amp\Pipeline\Queue;
use Amp\Serialization\SerializationException;
use Amp\Sync\Channel;
use Revolt\EventLoop;

/**
 * Gets or sets the global worker pool.
 *
 * @param WorkerPool|null $pool A worker pool instance.
 *
 * @return WorkerPool The global worker pool instance.
 */
function workerPool(?WorkerPool $pool = null): WorkerPool
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($pool) {
        return $map[$driver] = $pool;
    }

    return $map[$driver] ??= new DefaultWorkerPool();
}

/**
 * @template TResult
 * @template TReceive
 * @template TSend
 * @template TCache
 *
 * Executes a {@see Task} on the global worker pool.
 *
 * @param Task<TResult, TReceive, TSend, TCache> $task The task to execute.
 * @param Cancellation|null $cancellation Token to request cancellation. The task must support cancellation for
 * this to have any effect.
 *
 * @return Execution<TResult, TReceive, TSend, TCache>
 */
function submit(Task $task, ?Cancellation $cancellation = null): Execution
{
    return workerPool()->submit($task, $cancellation);
}

/**
 * Gets an available worker from the global worker pool.
 */
function getWorker(): Worker
{
    return workerPool()->getWorker();
}

/**
 * Creates a worker using the global worker factory.
 */
function createWorker(): Worker
{
    return workerFactory()->create();
}

/**
 * Gets or sets the global worker factory.
 */
function workerFactory(WorkerFactory $factory = null): WorkerFactory
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($factory) {
        return $map[$driver] = $factory;
    }

    return $map[$driver] ??= new DefaultWorkerFactory();
}

/**
 * Runs the tasks, receiving tasks from the parent and sending the result of those tasks.
 */
function runTasks(Channel $channel): void
{
    /** @var array<string, DeferredCancellation> $cancellationSources */
    $cancellationSources = [];

    /** @var array<string, Queue> $queues */
    $queues = [];

    while ($data = $channel->receive()) {
        // New Task execution request.
        if ($data instanceof Internal\TaskSubmission) {
            $id = $data->getId();

            $cancellationSources[$id] = $source = new DeferredCancellation;
            $queues[$id] = $queue = new Queue();

            $jobChannel = new JobChannel($id, $channel, $queue->iterate());

            EventLoop::queue(static function () use (
                &$cancellationSources,
                &$queues,
                $data,
                $id,
                $source,
                $queue,
                $jobChannel,
                $channel,
            ): void {
                try {
                    $result = $data->getTask()->run($jobChannel, $source->getCancellation());

                    if ($result instanceof Future) {
                        $result = $result->await($source->getCancellation());
                    }

                    $result = new Internal\TaskSuccess($data->getId(), $result);
                } catch (\Throwable $exception) {
                    if ($exception instanceof CancelledException && $source->isCancelled()) {
                        $result = new Internal\TaskCancelled($id, $exception);
                    } else {
                        $result = new Internal\TaskFailure($id, $exception);
                    }
                } finally {
                    $queue->complete();
                    unset($cancellationSources[$id], $queues[$id]);
                }

                try {
                    $channel->send($result);
                } catch (SerializationException $exception) {
                    // Could not serialize task result.
                    $channel->send(new Internal\TaskFailure($id, $exception));
                }
            });
            continue;
        }

        // Channel message.
        if ($data instanceof Internal\JobMessage) {
            ($queues[$data->getId()] ?? null)?->pushAsync($data->getMessage())->ignore();
            continue;
        }

        // Cancellation signal.
        if ($data instanceof Internal\JobCancellation) {
            ($cancellationSources[$data->getId()] ?? null)?->cancel();
            continue;
        }

        // Should not happen, but just in case...
        throw new \Error('Invalid value ' . \get_debug_type($data) . ' received in ' . __FUNCTION__);
    }
}
