<?php declare(strict_types = 1);

namespace Amp\Concurrent\Worker\Internal;

use Amp\Concurrent\{ Sync\Channel, Worker\Environment };
use Amp\{ Coroutine, Failure, Success };
use Interop\Async\Awaitable;

class TaskRunner {
    /**
     * @var \Amp\Concurrent\Sync\Channel
     */
    private $channel;

    /**
     * @var \Amp\Concurrent\Worker\Environment
     */
    private $environment;

    public function __construct(Channel $channel, Environment $environment) {
        $this->channel = $channel;
        $this->environment = $environment;
    }
    
    /**
     * Runs the task runner, receiving tasks from the parent and sending the result of those tasks.
     *
     * @return \Interop\Async\Awaitable
     */
    public function run(): Awaitable {
        return new Coroutine($this->execute());
    }
    
    /**
     * @coroutine
     *
     * @return \Generator
     */
    private function execute(): \Generator {
        $job = yield $this->channel->receive();

        while ($job instanceof Job) {
            $task = $job->getTask();
            
            try {
                $result = $task->run($this->environment);
                
                if ($result instanceof \Generator) {
                    $result = new Coroutine($result);
                }
                
                if (!$result instanceof Awaitable) {
                    $result = new Success($result);
                }
            } catch (\Throwable $exception) {
                $result = new Failure($exception);
            }
            
            $result->when(function ($exception, $value) use ($job) {
                if ($exception) {
                    $result = new TaskFailure($job->getId(), $exception);
                } else {
                    $result = new TaskSuccess($job->getId(), $value);
                }
    
                $this->channel->send($result);
            });

            $job = yield $this->channel->receive();
        }

        return $job;
    }
}
