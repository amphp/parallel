<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Stream\DuplexStream;
use Icicle\Stream\DuplexStreamInterface;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\WritableStreamInterface;
use Icicle\Tests\Concurrent\TestCase;

class ChannelTest extends TestCase
{
    public function testCreateSocketPair()
    {
        list($a, $b) = Channel::createSocketPair();

        $this->assertInternalType('resource', $a);
        $this->assertInternalType('resource', $b);
    }

    public function testIsOpen()
    {
        $mock = $this->getMock(DuplexStreamInterface::class);

        $mock->expects($this->once())
            ->method('isOpen')
            ->will($this->returnValue(true));

        $channel = new Channel($mock);
        $channel->isOpen();

        $readable = $this->getMock(ReadableStreamInterface::class);
        $writable = $this->getMock(WritableStreamInterface::class);

        $readable->expects($this->once())
            ->method('isOpen')
            ->will($this->returnValue(true));

        $writable->expects($this->once())
            ->method('isOpen')
            ->will($this->returnValue(true));

        $channel = new Channel($readable, $writable);
        $channel->isOpen();
    }

    public function testClose()
    {
        $mock = $this->getMock(DuplexStreamInterface::class);

        $mock->expects($this->once())
            ->method('close');

        $channel = new Channel($mock);
        $channel->close();

        $readable = $this->getMock(ReadableStreamInterface::class);
        $writable = $this->getMock(WritableStreamInterface::class);

        $readable->expects($this->once())
            ->method('close');

        $writable->expects($this->once())
            ->method('close');

        $channel = new Channel($readable, $writable);
        $channel->close();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testReadableWithoutWritable()
    {
        $mock = $this->getMock(ReadableStreamInterface::class);

        $channel = new Channel($mock);
    }

    public function testSendReceive()
    {
        Coroutine\create(function () {
            list($a, $b) = Channel::createSocketPair();
            $a = new Channel(new DuplexStream($a));
            $b = new Channel(new DuplexStream($b));

            yield $a->send('hello');
            $data = (yield $b->receive());
            $this->assertEquals('hello', $data);

            $a->close();
            $b->close();
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendReceive
     * @expectedException \Icicle\Concurrent\Exception\ChannelException
     */
    public function testInvalidDataReceived()
    {
        Coroutine\create(function () {
            list($a, $b) = Channel::createSocketPair();
            $a = new Channel($stream = new DuplexStream($a));
            $b = new Channel(new DuplexStream($b));

            // Close $a. $b should close on next read...
            yield $stream->write(pack('L', 10) . '1234567890');
            $data = (yield $b->receive());
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendReceive
     * @expectedException \Icicle\Concurrent\Exception\ChannelException
     */
    public function testSendUnserializableData()
    {
        Coroutine\create(function () {
            list($a, $b) = Channel::createSocketPair();
            $a = new Channel(new DuplexStream($a));
            $b = new Channel(new DuplexStream($b));

            // Close $a. $b should close on next read...
            yield $a->send(function () {});
            $data = (yield $b->receive());
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendReceive
     * @expectedException \Icicle\Concurrent\Exception\ChannelException
     */
    public function testSendAfterClose()
    {
        Coroutine\create(function () {
            list($a, $b) = Channel::createSocketPair();
            $a = new Channel(new DuplexStream($a));
            $b = new Channel(new DuplexStream($b));

            $a->close();

            // Close $a. $b should close on next read...
            yield $a->send('hello');
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendReceive
     * @expectedException \Icicle\Concurrent\Exception\ChannelException
     */
    public function testReceiveAfterClose()
    {
        Coroutine\create(function () {
            list($a, $b) = Channel::createSocketPair();
            $a = new Channel(new DuplexStream($a));
            $b = new Channel(new DuplexStream($b));

            $a->close();

            // Close $a. $b should close on next read...
            $data = (yield $a->receive());
        })->done();

        Loop\run();
    }
}
