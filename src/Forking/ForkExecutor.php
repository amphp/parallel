<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\ExecutorInterface;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Concurrent\Sync\ExitStatusInterface;

class ForkExecutor implements ExecutorInterface
{
    /**
     * @var \Icicle\Concurrent\Forking\Synchronized
     */
    private $synchronized;

    /**
     * @var \Icicle\Concurrent\Sync\ChannelInterface
     */
    private $channel;

    public function __construct(Synchronized $synchronized, ChannelInterface $channel)
    {
        $this->synchronized = $synchronized;
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
     * {@inheritdoc}
     */
    public function lock()
    {
        $this->synchronized->lock();
    }

    /**
     * {@inheritdoc}
     */
    public function unlock()
    {
        $this->synchronized->unlock();
    }

    /**
     * {@inheritdoc}
     */
    public function synchronized(callable $callback)
    {
        return $this->synchronized->synchronized($callback);
    }
}