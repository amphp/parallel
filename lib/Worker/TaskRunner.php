<?php

namespace Amp\Parallel\Worker;

use Amp\DeferredCancellation;
use Amp\CancelledException;
use Amp\Future;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\SerializationException;
use Revolt\EventLoop;

final class TaskRunner
{
    private Channel $channel;

    private Environment $environment;

    /** @var DeferredCancellation[] */
    private array $cancellationSources = [];

    public function __construct(Channel $channel, Environment $environment)
    {
        $this->channel = $channel;
        $this->environment = $environment;
    }

    /**
     * Runs the task runner, receiving tasks from the parent and sending the result of those tasks.
     */
    public function run(): int
    {
        while ($job = $this->channel->receive()) {
            if ($job instanceof Internal\Job) {
                $id = $job->getId();
                $this->cancellationSources[$id] = $source = new DeferredCancellation;

                EventLoop::queue(function () use ($job, $id, $source): void {
                    try {
                        $result = $job->getTask()->run($this->environment, $source->getCancellation());

                        if ($result instanceof Future) {
                            $result = $result->await($source->getCancellation());
                        }

                        $result = new Internal\TaskSuccess($job->getId(), $result);
                    } catch (\Throwable $exception) {
                        if ($exception instanceof CancelledException && $source->getCancellation()->isRequested()) {
                            $result = new Internal\TaskCancelled($id, $exception);
                        } else {
                            $result = new Internal\TaskFailure($id, $exception);
                        }
                    } finally {
                        unset($this->cancellationSources[$id]);
                    }

                    try {
                        $this->channel->send($result)->await();
                    } catch (SerializationException $exception) {
                        // Could not serialize task result.
                        $this->channel->send(new Internal\TaskFailure($id, $exception))->await();
                    }
                });
                continue;
            }

            // Cancellation signal.
            if (\is_string($job)) {
                ($this->cancellationSources[$job] ?? null)?->cancel();
                continue;
            }

            // Should not happen, but just in case...
            throw new \Error('Invalid value received in ' . self::class);
        }

        return 0;
    }
}
