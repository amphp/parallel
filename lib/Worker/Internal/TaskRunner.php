<?php

namespace Amp\Concurrent\Worker\Internal;

use Amp\Concurrent\Sync\Channel;
use Amp\Concurrent\Worker\{ Environment, Task };
use Amp\Coroutine;
use Interop\Async\Awaitable;

class TaskRunner {
    /**
     * @var bool
     */
    private $idle = true;

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
        $task = yield $this->channel->receive();

        while ($task instanceof Task) {
            $this->idle = false;

            try {
                $result = $task->run($this->environment);
                
                if ($result instanceof \Generator) {
                    $result = new Coroutine($result);
                }
                
                if ($result instanceof Awaitable) {
                    $result = yield $result;
                }
            } catch (\Throwable $exception) {
                $result = new TaskFailure($exception);
            }

            yield $this->channel->send($result);

            $this->idle = true;

            $task = yield $this->channel->receive();
        }

        return $task;
    }

    /**
     * @return bool
     */
    public function isIdle(): bool {
        return $this->idle;
    }
}
