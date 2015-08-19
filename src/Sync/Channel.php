<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\ChannelException;
use Icicle\Socket\Exception\Exception as SocketException;
use Icicle\Socket\Stream\DuplexStream;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 *
 * Note that the sockets are lazily bound to enable temporary thread safety. A
 * channel object can be safely transferred between threads up until the point
 * that the channel is used.
 */
class Channel implements ChannelInterface
{
    const HEADER_LENGTH = 4;

    /**
     * @var \Icicle\Socket\Stream\DuplexStream An asynchronous socket stream.
     */
    private $stream;

    /**
     * Creates a new channel instance.
     *
     * @param resource $socket
     */
    public function __construct($socket)
    {
        $this->stream = new DuplexStream($socket);
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
        if (!$this->stream->isWritable()) {
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
            yield $this->stream->write(pack('L', $length) . $serialized);
        } catch (SocketException $exception) {
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
        do {
            if (!$this->stream->isReadable()) {
                throw new ChannelException('The channel was unexpectedly closed. Did the context die?');
            }

            $buffer .= (yield $this->stream->read($length));
        } while (($length -= strlen($buffer)) > 0);

        list( , $length) = unpack('L', $buffer);
        $buffer = '';
        do {
            if (!$this->stream->isReadable()) {
                throw new ChannelException('The channel was unexpectedly closed. Did the context die?');
            }

            $buffer .= (yield $this->stream->read($length));
        } while (($length -= strlen($buffer)) > 0);

        // Attempt to unserialize the received data.
        try {
            yield unserialize($buffer);
        } catch (\Exception $exception) {
            throw new ChannelException('Received corrupt data from peer.', 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->stream->close();
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->stream->isOpen();
    }
}
