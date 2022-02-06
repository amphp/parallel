<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;;
use Amp\Parallel\Worker\Internal\JobChannel;
use Amp\Pipeline\Queue;
use Amp\Serialization\SerializationException;
use Amp\Sync\Channel;
use Revolt\EventLoop;

final class TaskRunner
{
    /** @var array<string, DeferredCancellation> */
    private array $cancellationSources = [];

    /** @var array<string, Queue> */
    private array $queues = [];

    public function __construct(
        private Channel $channel,
        private Cache $cache
    ) {
    }

    /**
     * Runs the task runner, receiving tasks from the parent and sending the result of those tasks.
     */
    public function run(): int
    {
        while ($data = $this->channel->receive()) {
            // New Task execution request.
            if ($data instanceof Internal\TaskEnqueue) {
                $id = $data->getId();
                $this->cancellationSources[$id] = $source = new DeferredCancellation;
                $this->queues[$id] = $queue = new Queue();
                $channel = new JobChannel($id, $this->channel, $queue->iterate(), static fn () => $source->cancel());

                EventLoop::queue(function () use ($data, $id, $source, $queue, $channel): void {
                    try {
                        $result = $data->getTask()->run($channel, $this->cache, $source->getCancellation());

                        if ($result instanceof Future) {
                            $result = $result->await($source->getCancellation());
                        }

                        $result = new Internal\TaskSuccess($data->getId(), $result);
                    } catch (\Throwable $exception) {
                        if ($exception instanceof CancelledException && $source->getCancellation()->isRequested()) {
                            $result = new Internal\TaskCancelled($id, $exception);
                        } else {
                            $result = new Internal\TaskFailure($id, $exception);
                        }
                    } finally {
                        $queue->complete();
                        unset($this->cancellationSources[$id], $this->queues[$id]);
                    }

                    try {
                        $this->channel->send($result);
                    } catch (SerializationException $exception) {
                        // Could not serialize task result.
                        $this->channel->send(new Internal\TaskFailure($id, $exception));
                    }
                });
                continue;
            }

            // Channel message.
            if ($data instanceof Internal\JobMessage) {
                ($this->queues[$data->getId()] ?? null)?->pushAsync($data->getMessage())->ignore();
                continue;
            }

            // Cancellation signal.
            if ($data instanceof Internal\JobCancellation) {
                ($this->cancellationSources[$data->getId()] ?? null)?->cancel();
                continue;
            }

            // Should not happen, but just in case...
            throw new \Error('Invalid value received in ' . self::class);
        }

        return 0;
    }
}
