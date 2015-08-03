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
    const MSG_DONE = 1;
    const MSG_ERROR = 2;
    const MSG_DATA = 3;

    private static $nextId = 1;

    private $socketResource;
    private $socket;

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

        $header = pack('CL', self::MSG_DATA, $length);
        $message = $header.$serialized;

        return $this->getSocket()->write($message);
    }

    /**
     * Waits asynchronously for a message from the peer.
     *
     * @return PromiseInterface
     */
    public function recieve()
    {
        return Coroutine\create(function () {
            // Read the message header first.
            $header = (yield $this->getSocket()->read(5));
            // Extract the data from the header.
            extract(unpack('Ctype/Llength', $header));

            // Read the serialized data from the socket.
            if ($type === self::MSG_DATA) {
                $serialized = (yield $this->socket->read($length));
                $data = unserialize($serialized);

                yield $data;
                return;
            }

            yield false;
        });
    }

    public function close()
    {
        $this->getSocket()->close();
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
