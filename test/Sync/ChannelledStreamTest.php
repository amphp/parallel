<?php

namespace Amp\Parallel\Test\Sync;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\OutputStream;
use Amp\Loop;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Amp\Success;

class ChannelledStreamTest extends TestCase {
    /**
     * @return \Amp\ByteStream\InputStream|\Amp\ByteStream\OutputStream
     */
    protected function createMockStream() {
        return new class implements InputStream, OutputStream {
            private $buffer = "";

            public function read(): Promise {
                $data = $this->buffer;
                $this->buffer = "";
                return new Success($data);
            }

            public function write(string $data): Promise {
                $this->buffer .= $data;
                return new Success(\strlen($data));
            }

            public function end(string $finalData = ""): Promise {
                throw new \BadMethodCallException;
            }

            public function close() {
                throw new \BadMethodCallException;
            }
        };
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
            $mock = $this->createMock(OutputStream::class);
            $mock->expects($this->once())
                ->method('write')
                ->will($this->throwException(new StreamException));

            $a = new ChannelledStream($this->createMock(InputStream::class), $mock);
            $b = new ChannelledStream(
                $this->createMock(InputStream::class),
                $this->createMock(OutputStream::class)
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
            $mock = $this->createMock(InputStream::class);
            $mock->expects($this->once())
                ->method('read')
                ->willReturn(new Success(null));

            $a = new ChannelledStream($mock, $this->createMock(OutputStream::class));

            $data = yield $a->receive();
        });

    }
}
