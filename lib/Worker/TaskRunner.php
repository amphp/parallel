<?php

namespace Amp\Parallel\Worker;

use Amp\Coroutine;
use Amp\Parallel\Sync\Channel;
use Amp\Promise;
use function Amp\call;

class TaskRunner {
    /** @var \Amp\Parallel\Sync\Channel */
    private $channel;

    /** @var \Amp\Parallel\Worker\Environment */
    private $environment;

    public function __construct(Channel $channel, Environment $environment) {
        $this->channel = $channel;
        $this->environment = $environment;
    }

    /**
     * Runs the task runner, receiving tasks from the parent and sending the result of those tasks.
     *
     * @return \Amp\Promise
     */
    public function run(): Promise {
        return new Coroutine($this->execute());
    }

    /**
     * @coroutine
     *
     * @return \Generator
     */
    private function execute(): \Generator {
        $job = yield $this->channel->receive();

        while ($job instanceof Internal\Job) {
            call([$job->getTask(), 'run'], $this->environment)->onResolve(function ($exception, $value) use ($job) {
                if ($exception) {
                    $result = new Internal\TaskFailure($job->getId(), $exception);
                } else {
                    $result = new Internal\TaskSuccess($job->getId(), $value);
                }

                $this->channel->send($result);
            });

            unset($job); // Free memory from last job.

            $job = yield $this->channel->receive();
        }

        return $job;
    }
}
