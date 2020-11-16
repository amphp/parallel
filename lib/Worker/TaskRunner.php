<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\SerializationException;
use Amp\Promise;
use function Amp\await;
use function Amp\defer;

final class TaskRunner
{
    private Channel $channel;

    private Environment $environment;

    /** @var CancellationTokenSource[] */
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
                $this->cancellationSources[$id] = $source = new CancellationTokenSource;

                defer(function () use ($job, $id, $source): void {
                    try {
                        $result = $job->getTask()->run($this->environment, $source->getToken());

                        if ($result instanceof Promise) {
                            $result = await($result);
                        }

                        $result = new Internal\TaskSuccess($job->getId(), $result);
                    } catch (\Throwable $exception) {
                        if ($exception instanceof CancelledException && $source->getToken()->isRequested()) {
                            $result = new Internal\TaskCancelled($id, $exception);
                        } else {
                            $result = new Internal\TaskFailure($id, $exception);
                        }
                    } finally {
                        unset($this->cancellationSources[$id]);
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

            // Cancellation signal.
            if (\is_string($job)) {
                if (isset($this->cancellationSources[$job])) {
                    $this->cancellationSources[$job]->cancel();
                }
                continue;
            }

            // Should not happen, but just in case...
            throw new \Error('Invalid value received in ' . self::class);
        }

        return 0;
    }
}
