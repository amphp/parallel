<?php
namespace Icicle\Concurrent\Worker\Internal;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\Task;

class TaskRunner
{
    /**
     * @var bool
     */
    private $idle = true;

    /**
     * @var \Icicle\Concurrent\Sync\Channel
     */
    private $channel;

    /**
     * @var \Icicle\Concurrent\Worker\Environment
     */
    private $environment;

    public function __construct(Channel $channel, Environment $environment)
    {
        $this->channel = $channel;
        $this->environment = $environment;
    }

    /**
     * @coroutine
     *
     * @return \Generator
     */
    public function run(): \Generator
    {
        $task = yield from $this->channel->receive();

        while ($task instanceof Task) {
            $this->idle = false;

            try {
                $result = yield from $task->run($this->environment);
            } catch (\Throwable $exception) {
                $result = new TaskFailure($exception);
            }

            yield from $this->channel->send($result);

            $this->idle = true;

            $task = yield from $this->channel->receive();
        }

        return $task;
    }

    /**
     * @return bool
     */
    public function isIdle(): bool
    {
        return $this->idle;
    }
}
