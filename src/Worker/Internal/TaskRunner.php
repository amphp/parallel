<?php
namespace Icicle\Concurrent\Worker\Internal;

use Icicle\Concurrent\ChannelInterface;
use Icicle\Concurrent\Worker\Environment;
use Icicle\Concurrent\Worker\TaskInterface;

class TaskRunner
{
    /**
     * @var bool
     */
    private $idle = true;

    /**
     * @var \Icicle\Concurrent\ChannelInterface
     */
    private $channel;

    /**
     * @var \Icicle\Concurrent\Worker\Environment
     */
    private $environment;

    public function __construct(ChannelInterface $channel, Environment $environment)
    {
        $this->channel = $channel;
        $this->environment = $environment;
    }

    /**
     * @coroutine
     *
     * @return \Generator
     */
    public function run()
    {
        $task = (yield $this->channel->receive());

        while ($task instanceof TaskInterface) {
            $this->idle = false;

            try {
                $result = (yield $task->run($this->environment));
            } catch (\Exception $exception) {
                $result = new TaskFailure($exception);
            }

            yield $this->channel->send($result);

            $this->idle = true;

            $task = (yield $this->channel->receive());
        }

        yield $task;
    }

    /**
     * @return bool
     */
    public function isIdle()
    {
        return $this->idle;
    }
}
