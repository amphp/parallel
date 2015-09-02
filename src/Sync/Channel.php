<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\ChannelException;
use Icicle\Concurrent\Exception\InvalidArgumentError;
use Icicle\Stream\Exception\Exception as StreamException;
use Icicle\Stream\DuplexStreamInterface;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\WritableStreamInterface;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 */
class Channel implements ChannelInterface
{
    const HEADER_LENGTH = 4;

    /**
     * @var \Icicle\Stream\ReadableStreamInterface
     */
    private $read;

    /**
     * @var \Icicle\Stream\WritableStreamInterface
     */
    private $write;

    /**
     * @var \Closure
     */
    private $errorHandler;

    /**
     * Creates a new channel instance.
     *
     * @param \Icicle\Stream\ReadableStreamInterface $read
     * @param \Icicle\Stream\WritableStreamInterface|null $write
     *
     * @throws \Icicle\Concurrent\Exception\InvalidArgumentError Thrown if no write stream is provided and the read
     *     stream is not a duplex stream.
     */
    public function __construct(ReadableStreamInterface $read, WritableStreamInterface $write = null)
    {
        if (null === $write) {
            if (!$read instanceof DuplexStreamInterface) {
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
     * Returns a pair of connected stream socket resources.
     *
     * Creates a new channel connection and returns two connections to the
     * channel. Each connection is a peer and interacts with the other, even
     * across threads or processes.
     *
     * @return resource[] Pair of socket resources.
     *
     * @throws \Icicle\Concurrent\Exception\ChannelException If creating the sockets fails.
     */
    public static function createSocketPair()
    {
        // Create a socket pair.
        if (($sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            throw new ChannelException('Failed to create channel sockets.');
        }

        return $sockets;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data)
    {
        if (!$this->write->isWritable()) {
            throw new ChannelException('The channel was unexpectedly closed. Did the context die?');
        }

        // Serialize the data to send into the channel.
        try {
            $serialized = serialize($data);
        } catch (\Exception $exception) {
            throw new ChannelException(
                'The given data cannot be sent because it is not serializable.', 0, $exception
            );
        }

        $length = strlen($serialized);

        try {
            yield $this->write->write(pack('L', $length) . $serialized);
        } catch (StreamException $exception) {
            throw new ChannelException('Sending on the channel failed. Did the context die?', 0, $exception);
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

        try {
            do {
                $buffer .= (yield $this->read->read($length));
            } while (($length -= strlen($buffer)) > 0);

            list(, $length) = unpack('L', $buffer);
            $buffer = '';

            do {
                $buffer .= (yield $this->read->read($length));
            } while (($length -= strlen($buffer)) > 0);
        } catch (StreamException $exception) {
            throw new ChannelException('Reading from the channel failed. Did the context die?', 0, $exception);
        }

        set_error_handler($this->errorHandler);

        // Attempt to unserialize the received data.
        try {
            yield unserialize($buffer);
        } catch (\Exception $exception) {
            throw new ChannelException('Exception thrown when unserializing data.', 0, $exception);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->read->close();

        if ($this->write !== $this->read) {
            $this->write->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->read->isOpen() && ($this->read === $this->write || $this->write->isOpen());
    }
}
