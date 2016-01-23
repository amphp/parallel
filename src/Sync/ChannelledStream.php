<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\{ChannelException, SerializationException};
use Icicle\Exception\InvalidArgumentError;
use Icicle\Stream\{DuplexStream, ReadableStream, WritableStream};

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 */
class ChannelledStream implements Channel
{
    const HEADER_LENGTH = 5;

    /**
     * @var \Icicle\Stream\ReadableStream
     */
    private $read;

    /**
     * @var \Icicle\Stream\WritableStream
     */
    private $write;

    /**
     * @var \Closure
     */
    private $errorHandler;

    /**
     * Creates a new channel instance.
     *
     * @param \Icicle\Stream\ReadableStream $read
     * @param \Icicle\Stream\WritableStream|null $write
     *
     * @throws \Icicle\Exception\InvalidArgumentError Thrown if no write stream is provided and the read
     *     stream is not a duplex stream.
     */
    public function __construct(ReadableStream $read, WritableStream $write = null)
    {
        if (null === $write) {
            if (!$read instanceof DuplexStream) {
                throw new InvalidArgumentError('Must provide a duplex stream if not providing a write stream.');
            }
            $this->write = $read;
        } else {
            $this->write = $write;
        }

        $this->read = $read;

        $this->errorHandler = function ($errno, $errstr) {
            throw new ChannelException(sprintf('Received corrupted data. Errno: %d; %s', $errno, $errstr));
        };
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): \Generator
    {
        // Serialize the data to send into the channel.
        try {
            $serialized = serialize($data);
        } catch (\Throwable $exception) {
            throw new SerializationException(
                'The given data cannot be sent because it is not serializable.', $exception
            );
        }

        $length = strlen($serialized);

        try {
            yield $this->write->write(pack('CL', 0, $length) . $serialized);
        } catch (\Throwable $exception) {
            throw new ChannelException('Sending on the channel failed. Did the context die?', $exception);
        }

        return $length;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(): \Generator
    {
        // Read the message length first to determine how much needs to be read from the stream.
        $length = self::HEADER_LENGTH;
        $buffer = '';
        $remaining = $length;

        try {
            do {
                $buffer .= yield from $this->read->read($remaining);
            } while ($remaining = $length - strlen($buffer));

            $data = unpack('Cprefix/Llength', $buffer);

            if (0 !== $data['prefix']) {
                throw new ChannelException('Invalid header received.');
            }

            $buffer = '';
            $remaining = $length = $data['length'];

            do {
                $buffer .= yield from $this->read->read($remaining);
            } while ($remaining = $length - strlen($buffer));
        } catch (\Throwable $exception) {
            throw new ChannelException('Reading from the channel failed. Did the context die?', $exception);
        }

        set_error_handler($this->errorHandler);

        // Attempt to unserialize the received data.
        try {
            $data = unserialize($buffer);
        } catch (\Throwable $exception) {
            throw new SerializationException('Exception thrown when unserializing data.', $exception);
        } finally {
            restore_error_handler();
        }

        return $data;
    }
}
