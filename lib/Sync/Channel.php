<?php

namespace Amp\Parallel\Sync;

use Amp\Cancellation;

/**
 * Interface for sending messages between execution contexts, such as two coroutines or two processes.
 *
 * @template TReceive
 * @template TSend
 */
interface Channel
{
    /**
     * @param Cancellation|null $cancellation Cancels waiting for the next value. Note the next value is not discarded
     * if the operation is cancelled, rather it will be returned from the next call to this method.
     *
     * @return TReceive|null Data received or null if the channel closed.
     *
     * @throws ChannelException If receiving from the channel fails.
     * @throws SerializationException If unserializing the data fails (if applicable to the implementation).
     */
    public function receive(?Cancellation $cancellation = null): mixed;

    /**
     * @param TSend $data
     *
     * @throws ChannelException If sending on the channel fails.
     * @throws SerializationException If serializing the data fails (if applicable to the implementation).
     */
    public function send(mixed $data): void;
}
