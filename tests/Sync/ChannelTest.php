<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Stream\DuplexStream;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\ReadableStream;
use Icicle\Tests\Concurrent\TestCase;

class ChannelTest extends TestCase
{
    /**
     * @return \Icicle\Stream\DuplexStream|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockStream()
    {
        $mock = $this->getMock(DuplexStream::class);

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

    /**
     * @expectedException \Icicle\Concurrent\Exception\InvalidArgumentError
     */
    public function testReadableWithoutWritable()
    {
        $mock = $this->getMock(ReadableStream::class);

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
            $mock = $this->getMock(DuplexStream::class);
            $mock->expects($this->once())
                ->method('write')
                ->will($this->throwException(new UnwritableException()));

            $a = new Channel($mock);
            $b = new Channel($this->getMock(DuplexStream::class));

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
            $mock = $this->getMock(DuplexStream::class);
            $mock->expects($this->once())
                ->method('read')
                ->will($this->throwException(new UnreadableException()));

            $a = new Channel($mock);

            $data = (yield $a->receive());
        })->done();

        Loop\run();
    }
}
