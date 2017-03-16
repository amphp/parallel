<?php

namespace Amp\Parallel\Test\Sync;

use Amp\ByteStream\ByteStream;
use Amp\ByteStream\ClosedException;
use Amp\Loop;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\Parallel\Test\TestCase;
use Amp\Success;

class ChannelledStreamTest extends TestCase {
    /**
     * @return \Amp\ByteStream\ByteStream|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockStream() {
        $mock = $this->createMock(ByteStream::class);

        $buffer = '';

        $mock->method('write')
            ->will($this->returnCallback(function ($data) use (&$buffer) {
                $buffer .= $data;
                return new Success(\strlen($data));
            }));

        $mock->method('read')
            ->will($this->returnCallback(function ($length, $byte = null, $timeout = 0) use (&$buffer) {
                $result = \substr($buffer, 0, $length);
                $buffer = \substr($buffer, $length);
                return new Success($result);
            }));

        return $mock;
    }
    
    public function testSendReceive() {
        Loop::run(function () {
            $mock = $this->createMockStream();
            $a = new ChannelledStream($mock);
            $b = new ChannelledStream($mock);

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
            $a = new ChannelledStream($mock);
            $b = new ChannelledStream($mock);

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
            $a = new ChannelledStream($mock);
            $b = new ChannelledStream($mock);

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
            $a = new ChannelledStream($mock);
            $b = new ChannelledStream($mock);

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
            $mock = $this->createMock(ByteStream::class);
            $mock->expects($this->once())
                ->method('write')
                ->will($this->throwException(new ClosedException));

            $a = new ChannelledStream($mock);
            $b = new ChannelledStream($this->createMock(ByteStream::class));

            yield $a->send('hello');
        });

    }

    /**
     * @depends testSendReceive
     * @expectedException \Amp\Parallel\ChannelException
     */
    public function testReceiveAfterClose() {
        Loop::run(function () {
            $mock = $this->createMock(ByteStream::class);
            $mock->expects($this->once())
                ->method('read')
                ->will($this->throwException(new ClosedException));

            $a = new ChannelledStream($mock);

            $data = yield $a->receive();
        });

    }
}
