<?php

namespace Amp\Parallel\Sync;

use Amp\ByteStream\ClosableStream;
use Amp\Cancellation;
use Amp\Pipeline\Emitter;

/**
 * Creates a channel where data sent is immediately receivable on the same channel.
 *
 * @template TValue
 * @template-implements Channel<TValue, TValue>
 */
final class LocalChannel implements Channel, ClosableStream
{
    /** @var Channel<TValue, TValue> */
    private Channel $channel;

    public function __construct(int $capacity = 0)
    {
        $emitter = new Emitter($capacity);
        $this->channel = new PipelineChannel($emitter->pipe(), $emitter);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function close(): void
    {
        $this->channel->close();
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->channel->receive($cancellation);
    }

    public function send(mixed $data): void
    {
        $this->channel->send($data);
    }
}
