<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\Parallel\Sync\SerializationException;
use Amp\PHPUnit\AsyncTestCase;

class ChannelledSocketTest extends AsyncTestCase
{
    /**
     * @return resource[]
     */
    protected function createSockets()
    {
        if (($sockets = @\stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            $message = "Failed to create socket pair";
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            $this->fail($message);
        }
        return $sockets;
    }

    public function testSendReceive()
    {
        list($left, $right) = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        $message = 'hello';

        yield $a->send($message);
        $data = yield $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testSendReceiveLongData()
    {
        list($left, $right) = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        $length = 0xffff;
        $message = '';
        for ($i = 0; $i < $length; ++$i) {
            $message .= \chr(\mt_rand(0, 255));
        }

        $a->send($message);
        $data = yield $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testInvalidDataReceived()
    {
        $this->expectException(ChannelException::class);

        list($left, $right) = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        \fwrite($left, \pack('L', 10) . '1234567890');
        $data = yield $b->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendUnserializableData()
    {
        $this->expectException(SerializationException::class);

        list($left, $right) = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        // Close $a. $b should close on next read...
        yield $a->send(function () {});
        $data = yield $b->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendAfterClose()
    {
        $this->expectException(ChannelException::class);

        list($left, $right) = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $a->close();

        yield $a->send('hello');
    }

    /**
     * @depends testSendReceive
     */
    public function testReceiveAfterClose()
    {
        $this->expectException(ChannelException::class);

        list($left, $right) = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $a->close();

        $data = yield $a->receive();
    }
}
