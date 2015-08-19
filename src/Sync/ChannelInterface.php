<?php
namespace Icicle\Concurrent\Sync;

interface ChannelInterface
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

    /**
     * @coroutine
     *
     * Waits asynchronously for a message from the peer.
     *
     * @return \Generator
     *
     * @resolve mixed
     *
     * @throws \Icicle\Concurrent\Exception\ChannelException
     */
    public function receive();

    /**
     * @coroutine
     *
     * Sends data across the channel to the peer.
     *
     * @param mixed $data The data to send.
     *
     * @return \Generator
     *
     * @resolve int Length of serialized data in bytes.
     *
     * @throws \Icicle\Concurrent\Exception\ChannelException
     */
    public function send($data);
}