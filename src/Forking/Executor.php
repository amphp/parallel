<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ChannelInterface;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Concurrent\Sync\ChannelInterface as SyncChannelInterface;
use Icicle\Concurrent\Sync\Internal\ExitStatusInterface;

class Executor implements ChannelInterface
{
    /**
     * @var \Icicle\Concurrent\Sync\ChannelInterface
     */
    private $channel;

    /**
     * @param \Icicle\Concurrent\Sync\ChannelInterface $channel
     */
    public function __construct(SyncChannelInterface $channel)
    {
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
}
