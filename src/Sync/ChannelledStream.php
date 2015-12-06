<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\ChannelException;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Stream\Exception\Exception as StreamException;
use Icicle\Stream\DuplexStream;
use Icicle\Stream\ReadableStream;
use Icicle\Stream\WritableStream;

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
     * @throws \Icicle\Concurrent\Exception\InvalidArgumentError Thrown if no write stream is provided and the read
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
    public function send($data)
    {
        // Serialize the data to send into the channel.
        try {
            $serialized = serialize($data);
        } catch (\Exception $exception) {
            throw new ChannelException(
                'The given data cannot be sent because it is not serializable.', $exception
            );
        }

        $length = strlen($serialized);

        try {
            yield $this->write->write(pack('CL', 0, $length) . $serialized);
        } catch (StreamException $exception) {
            throw new ChannelException('Sending on the channel failed. Did the context die?', $exception);
        }

        yield $length;
    }

    /**
     * {@inheritdoc}
     */
    public function receive()
    {
        // Read the message length first to determine how much needs to be read from the stream.
        $length = self::HEADER_LENGTH;
        $buffer = '';
        $remaining = $length;

        try {
            do {
                $buffer .= (yield $this->read->read($remaining));
            } while ($remaining = $length - strlen($buffer));

            $data = unpack('Cprefix/Llength', $buffer);

            if (0 !== $data['prefix']) {
                throw new ChannelException('Invalid header received.');
            }

            $buffer = '';
            $remaining = $length = $data['length'];

            do {
                $buffer .= (yield $this->read->read($remaining));
            } while ($remaining = $length - strlen($buffer));
        } catch (StreamException $exception) {
            throw new ChannelException('Reading from the channel failed. Did the context die?', $exception);
        }

        set_error_handler($this->errorHandler);

        // Attempt to unserialize the received data.
        try {
            $data = unserialize($buffer);
        } catch (\Exception $exception) {
            throw new ChannelException('Exception thrown when unserializing data.', $exception);
        } finally {
            restore_error_handler();
        }

        yield $data;
    }
}
