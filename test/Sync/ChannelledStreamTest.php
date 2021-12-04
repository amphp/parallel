<?php

namespace Amp\Parallel\Test\Sync;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\WritableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Future;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\Parallel\Sync\SerializationException;
use Amp\PHPUnit\AsyncTestCase;

class ChannelledStreamTest extends AsyncTestCase
{
    public function testSendReceive()
    {
        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);
        $b = new ChannelledStream($mock, $mock);

        $message = 'hello';

        $a->send($message);
        $data = $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testSendReceiveLongData()
    {
        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);
        $b = new ChannelledStream($mock, $mock);

        $length = 0xffff;
        $message = '';
        for ($i = 0; $i < $length; ++$i) {
            $message .= \chr(\mt_rand(0, 255));
        }

        $a->send($message);
        $data = $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testInvalidDataReceived()
    {
        $this->expectException(ChannelException::class);

        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);
        $b = new ChannelledStream($mock, $mock);

        // Close $a. $b should close on next read...
        $mock->write(\pack('L', 10) . '1234567890');
        $data = $b->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendUnserializableData()
    {
        $this->expectException(SerializationException::class);

        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);
        $b = new ChannelledStream($mock, $mock);

        // Close $a. $b should close on next read...
        $a->send(function () {
        });
        $data = $b->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendAfterClose()
    {
        $this->expectException(ChannelException::class);

        $mock = $this->createMock(WritableStream::class);
        $mock->expects($this->once())
            ->method('write')
            ->will($this->throwException(new StreamException));

        $a = new ChannelledStream($this->createMock(ReadableStream::class), $mock);
        $b = new ChannelledStream(
            $this->createMock(ReadableStream::class),
            $this->createMock(WritableStream::class)
        );

        $a->send('hello');
    }

    /**
     * @depends testSendReceive
     */
    public function testReceiveAfterClose()
    {
        $this->expectException(ChannelException::class);

        $mock = $this->createMock(ReadableStream::class);
        $mock->expects($this->once())
            ->method('read')
            ->willReturn(null);

        $a = new ChannelledStream($mock, $this->createMock(WritableStream::class));

        $data = $a->receive();
    }

    /**
     * @return ReadableStream|WritableStream
     */
    protected function createMockStream(): ReadableStream|WritableStream
    {
        return new class implements ReadableStream, WritableStream {
            private string $buffer = "";

            public function read(?Cancellation $cancellation = null): ?string
            {
                $data = $this->buffer;
                $this->buffer = "";
                return $data;
            }

            public function write(string $data): Future
            {
                $this->buffer .= $data;
                return Future::complete(null);
            }

            public function end(string $finalData = ""): Future
            {
                throw new \BadMethodCallException;
            }

            public function close()
            {
                throw new \BadMethodCallException;
            }

			public function isReadable(): bool
			{
				return true;
			}

			public function isWritable(): bool
			{
				return true;
			}
		};
    }
}
