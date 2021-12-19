<?php

namespace Amp\Parallel\Sync;

use Amp\ByteStream\ClosableStream;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\ResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Cancellation;
use Amp\Serialization\Serializer;

/**
 * Uses stream resources to create a Channel.
 *
 * @template TValue
 * @template-implements Channel<TValue>
 */
final class ChannelledSocket implements Channel, ClosableStream, ResourceStream
{
    private ChannelledStream $channel;

    private ReadableResourceStream $read;

    private WritableResourceStream $write;

    /**
     * @param resource $read Readable stream resource.
     * @param resource $write Writable stream resource.
     * @param Serializer|null $serializer
     *
     * @throws \Error If a stream resource is not given for $resource.
     */
    public function __construct($read, $write, ?Serializer $serializer = null)
    {
        $this->channel = new ChannelledStream(
            $this->read = new ReadableResourceStream($read),
            $this->write = new WritableResourceStream($write),
            $serializer
        );
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        return $this->channel->receive($cancellation);
    }

    public function send(mixed $data): void
    {
        $this->channel->send($data);
    }

    public function unreference(): void
    {
        $this->read->unreference();
        $this->write->unreference();
    }

    public function reference(): void
    {
        $this->read->reference();
        $this->write->unreference();
    }

    /**
     * @return resource|null
     */
    public function getResource()
    {
        return $this->read->getResource();
    }

    public function isClosed(): bool
    {
        return $this->read->isClosed() || $this->write->isClosed();
    }

    /**
     * Closes the read and write resource streams.
     */
    public function close(): void
    {
        $this->read->close();
        $this->write->close();
    }
}
