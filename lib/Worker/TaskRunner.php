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
                asyncCall(function () use ($job): \Generator {
                    $id = $job->getId();
                    $this->cancellationSources[$id] = $source = new CancellationTokenSource;
                    try {
                        $result = new Internal\TaskSuccess(
                            $job->getId(),
                            yield call([$job->getTask(), 'run'], $this->environment, $source->getToken())
                        );
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
                        yield $this->channel->send($result);
                    } catch (SerializationException $exception) {
                        // Could not serialize task result.
                        yield $this->channel->send(new Internal\TaskFailure($id, $exception));
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
    }
}
