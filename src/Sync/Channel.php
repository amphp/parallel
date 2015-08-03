<?php
namespace Icicle\Concurrent\Sync;

use Icicle\Concurrent\Exception\ChannelException;
use Icicle\Coroutine;
use Icicle\Socket\Stream\DuplexStream;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 */
class Channel
{
    const MESSAGE_DONE = 1;
    const MESSAGE_ERROR = 2;
    const MESSAGE_DATA = 3;

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

        return array_map(static function ($socket) {
            return new static($socket);
        }, $sockets);
    }

    /**
     * Sends data across the channel to the peer.
     *
     * @param mixed $data The data to send.
     *
     * @return PromiseInterface
     */
    public function send($data)
    {
        $serialized = serialize($data);
        $length = strlen($serialized);

        $header = pack('CL', self::MESSAGE_DATA, $length);
        $message = $header.$serialized;

        return $this->getSocket()->write($message);
    }

    /**
     * Waits asynchronously for a message from the peer.
     *
     * @return PromiseInterface
     */
    public function receive()
    {
        return Coroutine\create(function () {
            // Read the message header first and extract its contents.
            $header = unpack('Ctype/Llength', (yield $this->getSocket()->read(5)));

            // If the message type is MESSAGE_DONE, the peer was closed and the channel
            // is done.
            if ($header['type'] === self::MESSAGE_DONE) {
                $this->getSocket()->close();
                return;
            }

            // Read the serialized data from the socket.
            if ($header['type'] === self::MESSAGE_DATA) {
                $serialized = (yield $this->socket->read($header['length']));
                $data = unserialize($serialized);

                yield $data;
                return;
            }

            yield false;
        });
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
        $message = pack('Cx4', self::MESSAGE_DONE);

        return $this->getSocket()->write($message)->then(function () {
            $this->getSocket()->close();
        });
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
    private function __construct($socketResource)
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
