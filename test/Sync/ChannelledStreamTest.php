<?php declare(strict_types = 1);

namespace Amp\Concurrent\Test\Sync;

use Amp\Concurrent\Sync\ChannelledStream;
use Amp\Stream\Stream;
use Amp\Stream\ClosedException;
use Amp\Concurrent\Test\TestCase;
use Amp\Success;

class ChannelledStreamTest extends TestCase {
    /**
     * @return \Amp\Stream\Stream|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockStream() {
        $mock = $this->createMock(Stream::class);

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
        \Amp\execute(function () {
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
        \Amp\execute(function () {
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
     * @expectedException \Amp\Concurrent\ChannelException
     */
    public function testInvalidDataReceived() {
        \Amp\execute(function () {
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
     * @expectedException \Amp\Concurrent\ChannelException
     */
    public function testSendUnserializableData() {
        \Amp\execute(function () {
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
     * @expectedException \Amp\Concurrent\ChannelException
     */
    public function testSendAfterClose() {
        \Amp\execute(function () {
            $mock = $this->createMock(Stream::class);
            $mock->expects($this->once())
                ->method('write')
                ->will($this->throwException(new ClosedException));

            $a = new ChannelledStream($mock);
            $b = new ChannelledStream($this->createMock(Stream::class));

            yield $a->send('hello');
        });

    }

    /**
     * @depends testSendReceive
     * @expectedException \Amp\Concurrent\ChannelException
     */
    public function testReceiveAfterClose() {
        \Amp\execute(function () {
            $mock = $this->createMock(Stream::class);
            $mock->expects($this->once())
                ->method('read')
                ->will($this->throwException(new ClosedException));

            $a = new ChannelledStream($mock);

            $data = yield $a->receive();
        });

    }
}
