<?php

namespace Amp\Parallel\Sync;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Serialization\Serializer;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 */
final class ChannelledStream implements Channel
{
    private ReadableStream $read;

    private WritableStream $write;

    private \SplQueue $received;

    private ChannelParser $parser;

    /**
     * Creates a new channel from the given stream objects. Note that $read and $write can be the same object.
     *
     * @param ReadableStream $read
     * @param WritableStream $write
     * @param Serializer|null $serializer
     */
    public function __construct(ReadableStream $read, WritableStream $write, ?Serializer $serializer = null)
    {
        $this->read = $read;
        $this->write = $write;
        $this->received = new \SplQueue;
        $this->parser = new ChannelParser([$this->received, 'push'], $serializer);
    }

    public function send(mixed $data): void
    {
        $data = $this->parser->encode($data);

        try {
            $this->write->write($data);
        } catch (\Throwable $exception) {
            throw new ChannelException("Sending on the channel failed. Did the context die?", 0, $exception);
        }
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        while ($this->received->isEmpty()) {
            try {
                $chunk = $this->read->read($cancellation);
            } catch (StreamException $exception) {
                throw new ChannelException("Reading from the channel failed. Did the context die?", 0, $exception);
            }

            if ($chunk === null) {
                throw new ChannelException("The channel closed unexpectedly. Did the context die?");
            }

            $this->parser->push($chunk);
        }

        return $this->received->shift();
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
