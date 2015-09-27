<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Socket;
use Icicle\Stream\DuplexStreamInterface;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\WritableStreamInterface;
use Icicle\Tests\Concurrent\TestCase;

class ChannelTest extends TestCase
{
    /**
     * @return \Icicle\Stream\DuplexStreamInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockStream()
    {
        $mock = $this->getMock(DuplexStreamInterface::class);

        $buffer = '';

        $mock->method('write')
            ->will($this->returnCallback(function ($data) use (&$buffer) {
                $buffer .= $data;
            }));

        $mock->method('read')
            ->will($this->returnCallback(function ($length, $byte = null, $timeout = 0) use (&$buffer) {
                $result = substr($buffer, 0, $length);
                $buffer = substr($buffer, $length);
                return $result;
            }));

        return $mock;
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
            $mock = $this->createMockStream();
            $a = new Channel($mock);
            $b = new Channel($mock);

            $message = 'hello';

            yield $a->send($message);
            $data = (yield $b->receive());
            $this->assertSame($message, $data);

            $a->close();
            $b->close();
        })->done();

        Loop\run();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendReceiveLongData()
    {
        Coroutine\create(function () {
            $mock = $this->createMockStream();
            $a = new Channel($mock);
            $b = new Channel($mock);

            $length = 0xffff;
            $message = '';
            for ($i = 0; $i < $length; ++$i) {
                $message .= chr(mt_rand(0, 255));
            }

            yield $a->send($message);
            $data = (yield $b->receive());
            $this->assertSame($message, $data);

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
            $mock = $this->createMockStream();
            $a = new Channel($mock);
            $b = new Channel($mock);

            // Close $a. $b should close on next read...
            yield $mock->write(pack('L', 10) . '1234567890');
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
            $mock = $this->createMockStream();
            $a = new Channel($mock);
            $b = new Channel($mock);

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
            $mock = $this->getMock(DuplexStreamInterface::class);
            $mock->expects($this->once())
                ->method('write')
                ->will($this->throwException(new UnwritableException()));

            $a = new Channel($mock);
            $b = new Channel($this->getMock(DuplexStreamInterface::class));

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
            $mock = $this->getMock(DuplexStreamInterface::class);
            $mock->expects($this->once())
                ->method('read')
                ->will($this->throwException(new UnreadableException()));

            $a = new Channel($mock);

            $data = (yield $a->receive());
        })->done();

        Loop\run();
    }
}
