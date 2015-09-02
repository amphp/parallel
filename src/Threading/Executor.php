<?php
namespace Icicle\Concurrent\Threading;

use Icicle\Concurrent\ChannelInterface;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Sync\Internal\ExitStatusInterface;
use Icicle\Concurrent\Sync;
use Icicle\Concurrent\SynchronizableInterface;
use Icicle\Coroutine;

class Executor implements ChannelInterface, SynchronizableInterface
{
    const LATENCY_TIMEOUT = 0.01; // 10 ms

    /**
     * @var \Icicle\Concurrent\Threading\Internal\Thread
     */
    private $thread;

    /**
     * @var \Icicle\Concurrent\ChannelInterface
     */
    private $channel;

    /**
     * @param \Icicle\Concurrent\Threading\Internal\Thread $thread
     * @param \Icicle\Concurrent\Sync\ChannelInterface $channel
     */
    public function __construct(Internal\Thread $thread, Sync\ChannelInterface $channel)
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
