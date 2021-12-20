<?php

namespace Amp\Parallel\Sync;

use Amp\ByteStream\ClosableStream;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Pipeline\Pipeline;
use Amp\Serialization\Serializer;
use function Amp\Pipeline\fromIterable;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 *
 * @template TReceive
 * @template TSend
 * @template-implements Channel<TReceive, TSend>
 */
final class ChannelledStream implements Channel, ClosableStream
{
    private ReadableStream $read;

    private WritableStream $write;

    private ChannelParser $parser;

    /** @var Pipeline<TReceive> */
    private Pipeline $pipeline;

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

        $received = new \SplQueue();
        $this->parser = $parser = new ChannelParser(\Closure::fromCallable([$received, 'push']), $serializer);

        $this->pipeline = fromIterable(static function () use ($read, $received, $parser): \Generator {
            while (true) {
                try {
                    $chunk = $read->read();
                } catch (StreamException $exception) {
                    throw new ChannelException(
                        "Reading from the channel failed. Did the context die?",
                        0,
                        $exception,
                    );
                }

                if ($chunk === null) {
                    return;
                }

                $parser->push($chunk);

                while (!$received->isEmpty()) {
                    yield $received->shift();
                }
            }
        });
    }

    public function __destruct()
    {
        $this->close();
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
        return $this->pipeline->continue($cancellation);
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
