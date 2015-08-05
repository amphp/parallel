<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\ChannelException;
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
class Channel
{
    const MESSAGE_CLOSE = 1;
    const MESSAGE_DATA = 2;

    const HEADER_LENGTH = 5;

    /**
     * @var DuplexStream An asynchronous socket stream.
     */
    private $socket;

    /**
     * @var resource A synchronous socket stream.
     */
    private $socketResource;

    /**
     * Creates a new channel and returns a pair of connections.
     *
     * Creates a new channel connection and returns two connections to the
     * channel. Each connection is a peer and interacts with the other, even
     * across threads or processes.
     *
     * @return [Channel, Channel] A pair of channels.
     */
    public static function create()
    {
        // Create a socket pair.
        if (($sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            throw new ChannelException('Failed to create channel sockets.');
        }

        return $sockets;
    }

    /**
     * Sends data across the channel to the peer.
     *
     * @param mixed $data The data to send.
     *
     * @return Generator
     */
    public function send($data)
    {
        // Serialize the data to send into the channel.
        try {
            $serialized = serialize($data);
        } catch (\Exception $exception) {
            throw new ChannelException('The given data is not sendable because it is not serializable.',
                0, $exception);
        }
        $length = strlen($serialized);

        $header = pack('CL', self::MESSAGE_DATA, $length);
        $message = $header.$serialized;

        yield $this->getSocket()->write($message);
    }

    /**
     * Waits asynchronously for a message from the peer.
     *
     * @return Generator
     */
    public function receive()
    {
        // Read the message header first and extract its contents.
        $buffer = '';
        $length = self::HEADER_LENGTH;
        do {
            $buffer .= (yield $this->getSocket()->read($length));
        } while (($length -= strlen($buffer)) > 0);

        $header = unpack('Ctype/Llength', $buffer);

        // If the message type is MESSAGE_CLOSE, the peer was closed and the channel
        // is done.
        if ($header['type'] === self::MESSAGE_CLOSE) {
            $this->getSocket()->close();
            yield null;
            return;
        }

        // Read the serialized data from the socket.
        if ($header['type'] === self::MESSAGE_DATA) {
            $buffer = '';
            $length = $header['length'];
            do {
                $buffer .= (yield $this->getSocket()->read($length));
            } while (($length -= strlen($buffer)) > 0);

            // Attempt to unserialize the received data.
            try {
                yield unserialize($buffer);
            } catch (\Exception $exception) {
                throw new ChannelException('Received corrupt data from peer.', 0, $exception);
            }
        }
    }

    /**
     * Closes the channel.
     *
     * This method closes the connection to the peer and sends a message to the
     * peer notifying that the connection has been closed.
     *
     * @return PromiseInterface
     */
    public function close()
    {
        // Create a message with just a DONE header and zero data.
        $message = pack('Cx4', self::MESSAGE_CLOSE);

        return $this->getSocket()->end($message);
    }

    /**
     * Checks if the channel is still open.
     *
     * @return bool True if the channel is open, otherwise false.
     */
    public function isOpen()
    {
        return $this->getSocket()->isOpen();
    }

    /**
     * Creates a new channel instance.
     *
     * @param resource $socketResource
     */
    public function __construct($socketResource)
    {
        $this->socketResource = $socketResource;
    }

    /**
     * Gets an asynchronous socket instance.
     *
     * @return DuplexStream
     */
    private function getSocket()
    {
        if ($this->socket === null) {
            $this->socket = new DuplexStream($this->socketResource);
        }

        return $this->socket;
    }
}
