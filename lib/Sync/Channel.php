<?php

namespace Amp\Parallel\Sync;

use Amp\CancellationToken;
use Amp\Parallel\Context\StatusError;

/**
 * Interface for sending messages between execution contexts.
 */
interface Channel
{
    /**
     * @param CancellationToken|null $token Cancels waiting for the next value. Note the next value is not discarded
     * if the operation is cancelled, rather it will be returned from the next call to this method.
     *
     * @return mixed Data received.
     *
     * @throws StatusError Thrown if the context has not been started.
     * @throws SynchronizationError If the context has not been started or the context
     *     unexpectedly ends.
     * @throws ChannelException If receiving from the channel fails.
     * @throws SerializationException If unserializing the data fails.
     */
    public function receive(?CancellationToken $token = null): mixed;

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
