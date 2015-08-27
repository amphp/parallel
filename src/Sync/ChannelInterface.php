<?php
namespace Icicle\Concurrent\Sync;

interface ChannelInterface extends \Icicle\Concurrent\ChannelInterface
{
    /**
     * Determines if the channel is open.
     *
     * @return bool
     */
    public function isOpen();

    /**
     * Closes the channel.
     */
    public function close();
}