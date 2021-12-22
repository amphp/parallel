<?php

namespace Amp\Parallel\Worker;

use Amp\Cache\Cache;
use Amp\DeferredCancellation;
use Amp\CancelledException;
use Amp\Future;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Worker\Internal\JobChannel;
use Amp\Pipeline\Emitter;
use Revolt\EventLoop;

final class TaskRunner
{
    /** @var array<string, DeferredCancellation> */
    private array $cancellationSources = [];

    /** @var array<string, Emitter> */
    private array $emitters = [];

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
                $this->emitters[$id] = $emitter = new Emitter();
                $channel = new JobChannel($id, $this->channel, $emitter->pipe());

                EventLoop::queue(function () use ($data, $id, $source, $emitter, $channel): void {
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
                        $emitter->complete();
                        unset($this->cancellationSources[$id], $this->emitters[$id]);
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
                ($this->emitters[$data->getId()] ?? null)?->emit($data->getMessage())->ignore();
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
