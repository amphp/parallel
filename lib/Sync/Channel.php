<?php

namespace Amp\Parallel\Sync;

use Amp\Parallel\Context\StatusError;

/**
 * Interface for sending messages between execution contexts.
 */
interface Channel
{
    /**
     * @return mixed Data received.
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws ChannelException If receiving from the channel fails.
     * @throws SerializationException If unserializing the data fails.
     */
    public function receive(): mixed;

    /**
     * @param mixed $data
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws ChannelException If sending on the channel fails.
     * @throws \Error If an ExitResult object is given.
     * @throws SerializationException If serializing the data fails.
     */
    public function send(mixed $data): void;
}
