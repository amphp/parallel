<?php
namespace Icicle\Concurrent\Forking;

use Icicle\Concurrent\ExecutorInterface;
use Icicle\Concurrent\Sync\Channel;

class ForkExecutor implements ExecutorInterface
{
    /**
     * @var \Icicle\Concurrent\Forking\Synchronized
     */
    private $synchronized;

    /**
     * @var \Icicle\Concurrent\Sync\Channel
     */
    private $channel;

    public function __construct(Synchronized $synchronized, Channel $channel)
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
        return $this->channel->send($data);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->channel->close();
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