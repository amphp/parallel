<?php
namespace Icicle\Concurrent\Sync;

/**
 * Interface for sending messages between execution contexts.
 */
interface ChannelInterface
{
    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve mixed
     *
     * @throws \Icicle\Concurrent\Exception\StatusError Thrown if the context has not been started.
     * @throws \Icicle\Concurrent\Exception\SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws \Icicle\Concurrent\Exception\ChannelException If receiving from the channel fails.
     */
    public function receive();

    /**
     * @coroutine
     *
     * @param mixed $data
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @throws \Icicle\Concurrent\Exception\StatusError Thrown if the context has not been started.
     * @throws \Icicle\Concurrent\Exception\SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws \Icicle\Concurrent\Exception\ChannelException If sending on the channel fails.
     * @throws \Icicle\Concurrent\Exception\InvalidArgumentError If an ExitStatusInterface object is given.
     */
    public function send($data);
}
