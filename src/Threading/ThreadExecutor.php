<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\ExecutorInterface;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Concurrent\Sync\ExitStatusInterface;
use Icicle\Coroutine;

class ThreadExecutor implements ExecutorInterface
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var \Icicle\Concurrent\Threading\InternalThread
     */
    private $thread;

    /**
     * @var \Icicle\Concurrent\ChannelInterface
     */
    private $channel;

    /**
     * @param \Icicle\Concurrent\Threading\InternalThread
     * @param \Icicle\Concurrent\Sync\ChannelInterface $channel
     */
    public function __construct(InternalThread $thread, ChannelInterface $channel)
    {
        $this->thread = $thread;
        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function receive()
    {
        return $this->channel->receive();
    }

    /**
     * {@inheritdoc}
     */
    public function send($data)
    {
        if ($data instanceof ExitStatusInterface) {
            throw new InvalidArgumentError('Cannot send exit status objects.');
        }

        yield $this->channel->send($data);
    }

    /**
     * @param callable $callback
     *
     * @return \Generator
     */
    public function synchronized(callable $callback)
    {
        while (!$this->thread->tsl()) {
            yield Coroutine\sleep(self::LATENCY_TIMEOUT);
        }

        try {
            yield $callback($this);
        } finally {
            $this->thread->release();
        }
    }
}
