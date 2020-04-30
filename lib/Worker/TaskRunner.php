<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Coroutine;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\SerializationException;
use Amp\Promise;
use function Amp\asyncCall;
use function Amp\call;

final class TaskRunner
{
    /** @var Channel */
    private $channel;

    /** @var Environment */
    private $environment;

    /** @var CancellationTokenSource[] */
    private $cancellationSources = [];

    public function __construct(Channel $channel, Environment $environment)
    {
        $this->channel = $channel;
        $this->environment = $environment;
    }

    /**
     * Runs the task runner, receiving tasks from the parent and sending the result of those tasks.
     *
     * @return Promise<void>
     */
    public function run(): Promise
    {
        return new Coroutine($this->execute());
    }

    /**
     * @return \Generator
     */
    private function execute(): \Generator
    {
        while ($job = yield $this->channel->receive()) {
            if ($job instanceof Internal\Job) {
                $this->cancellationSources[$job->getId()] = $source = new CancellationTokenSource;
                asyncCall(function () use ($job, $source): \Generator {
                    try {
                        $result = new Internal\TaskSuccess(
                            $job->getId(),
                            yield call([$job->getTask(), "run"], $this->environment, $source->getToken())
                        );
                    } catch (\Throwable $exception) {
                        if ($exception instanceof CancelledException && $source->getToken()->isRequested()) {
                            $result = new Internal\TaskCancelled($job->getId(), $exception);
                        } else {
                            $result = new Internal\TaskFailure($job->getId(), $exception);
                        }
                    } finally {
                        unset($this->cancellationSources[$job->getId()]);
                    }

                    try {
                        yield $this->channel->send($result);
                    } catch (SerializationException $exception) {
                        // Could not serialize task result.
                        yield $this->channel->send(new Internal\TaskFailure($result->getId(), $exception));
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

            // Shutdown signal.
            if ($job === null) {
                return;
            }

            // Should not happen, but just in case...
            throw new \Error('Invalid value received in ' . self::class);
        }
    }
}
