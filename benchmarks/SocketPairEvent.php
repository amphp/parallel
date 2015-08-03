<?php
namespace Icicle\Benchmarks\Concurrent;

use Athletic\AthleticEvent;

/**
 * Profiles sending and receiving serialized data across a local TCP socket.
 */
class SocketPairEvent extends AthleticEvent
{
    private $sockets;

    public function classSetUp()
    {
        $this->sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }

    public function classTearDown()
    {
        fclose($this->sockets[0]);
        fclose($this->sockets[1]);
    }

    /**
     * @iterations 1000
     */
    public function writeBool()
    {
        $this->write(true);
        $this->read();
    }

    /**
     * @iterations 1000
     */
    public function writeInt()
    {
        $this->write(2);
        $this->read();
    }

    /**
     * @iterations 1000
     */
    public function writeString()
    {
        $this->write('world');
        $this->read();
    }

    /**
     * @iterations 1000
     */
    public function writeObject()
    {
        $this->write(new \stdClass());
        $this->read();
    }

    private function read()
    {
        $buffer = '';

        while (true) {
            $char = fgetc($this->sockets[1]);
            if ($char !== chr(0)) {
                $buffer .= $char;
            }
            break;
        }

        return unserialize($buffer);
    }

    private function write($value)
    {
        fwrite($this->sockets[0], serialize($value) . chr(0));
    }
}
