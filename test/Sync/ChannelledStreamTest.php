<?php

namespace Amp\Parallel\Test\Sync;

use Amp\ByteStream\DuplexStream;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;
use Amp\Loop;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\PHPUnit\TestCase;
use Amp\Success;

class ChannelledStreamTest extends TestCase {
    /**
     * @return \Amp\ByteStream\DuplexStream|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockStream() {
        $mock = $this->createMock(DuplexStream::class);

        $buffer = '';

        $mock->method('write')
            ->will($this->returnCallback(function ($data) use (&$buffer) {
                $buffer .= $data;
                return new Success(\strlen($data));
            }));

        $mock->method('advance')
            ->willReturn(new Success(true));

        $mock->method('getChunk')
            ->will($this->returnCallback(function () use (&$buffer) {
                $result = $buffer;
                $buffer = '';
                return $result;
            }));

        return $mock;
    }
    
    public function testSendReceive() {
        Loop::run(function () {
            $mock = $this->createMockStream();
            $a = new ChannelledStream($mock, $mock);
            $b = new ChannelledStream($mock, $mock);

            $message = 'hello';

            yield $a->send($message);
            $data = yield $b->receive();
            $this->assertSame($message, $data);
        });
    }

    /**
     * @depends testSendReceive
     */
    public function testSendReceiveLongData() {
        Loop::run(function () {
            $mock = $this->createMockStream();
            $a = new ChannelledStream($mock, $mock);
            $b = new ChannelledStream($mock, $mock);

            $length = 0xffff;
            $message = '';
            for ($i = 0; $i < $length; ++$i) {
                $message .= chr(mt_rand(0, 255));
            }

            yield $a->send($message);
            $data = yield $b->receive();
            $this->assertSame($message, $data);
        });

    }

    /**
     * @depends testSendReceive
     * @expectedException \Amp\Parallel\ChannelException
     */
    public function testInvalidDataReceived() {
        Loop::run(function () {
            $mock = $this->createMockStream();
            $a = new ChannelledStream($mock, $mock);
            $b = new ChannelledStream($mock, $mock);

            // Close $a. $b should close on next read...
            yield $mock->write(pack('L', 10) . '1234567890');
            $data = yield $b->receive();
        });

    }

    /**
     * @depends testSendReceive
     * @expectedException \Amp\Parallel\ChannelException
     */
    public function testSendUnserializableData() {
        Loop::run(function () {
            $mock = $this->createMockStream();
            $a = new ChannelledStream($mock, $mock);
            $b = new ChannelledStream($mock, $mock);

            // Close $a. $b should close on next read...
            yield $a->send(function () {});
            $data = yield $b->receive();
        });

    }

    /**
     * @depends testSendReceive
     * @expectedException \Amp\Parallel\ChannelException
     */
    public function testSendAfterClose() {
        Loop::run(function () {
            $mock = $this->createMock(DuplexStream::class);
            $mock->expects($this->once())
                ->method('write')
                ->will($this->throwException(new StreamException));

            $a = new ChannelledStream($mock, $mock);
            $b = new ChannelledStream(
                $this->createMock(ReadableStream::class),
                $this->createMock(WritableStream::class)
            );

            yield $a->send('hello');
        });

    }

    /**
     * @depends testSendReceive
     * @expectedException \Amp\Parallel\ChannelException
     */
    public function testReceiveAfterClose() {
        Loop::run(function () {
            $mock = $this->createMock(DuplexStream::class);
            $mock->expects($this->once())
                ->method('advance')
                ->willReturn(new Success(false));

            $a = new ChannelledStream($mock, $mock);

            $data = yield $a->receive();
        });

    }
}
