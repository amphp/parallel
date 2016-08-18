<?php

namespace Amp\Concurrent\Sync;

use Interop\Async\Awaitable;

/**
 * Interface for sending messages between execution contexts.
 */
interface Channel {
    /**
     * @return \Interop\Async\Awaitable<mixed>
     *
     * @throws \Amp\Concurrent\StatusError Thrown if the context has not been started.
     * @throws \Amp\Concurrent\SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws \Amp\Concurrent\ChannelException If receiving from the channel fails.
     * @throws \Amp\Concurrent\SerializationException If unserializing the data fails.
     */
    public function receive(): Awaitable;

    /**
     * @param mixed $data
     *
     * @return \Interop\Async\Awaitable<int> Resolves with the number of bytes sent on the channel.
     *
     * @throws \Amp\Concurrent\StatusError Thrown if the context has not been started.
     * @throws \Amp\Concurrent\SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws \Amp\Concurrent\ChannelException If sending on the channel fails.
     * @throws \Error If an ExitStatus object is given.
     * @throws \Amp\Concurrent\SerializationException If serializing the data fails.
     */
    public function send($data): Awaitable;
}
