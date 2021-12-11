<?php

namespace Amp\Parallel\Test\Sync;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Parallel\Sync\LocalChannel;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\async;

class LocalChannelTest extends AsyncTestCase
{
    private LocalChannel $channel;

    public function setUp(): void
    {
        parent::setUp();
        $this->channel = new LocalChannel();
    }

    public function testSendReceive(): void
    {
        $values = [1, 2, 3];
        \array_walk($values, fn (int $value) => async(fn () => $this->channel->send($value))->ignore());
        \array_walk($values, fn (int $value) => self::assertSame($value, $this->channel->receive()));
    }

    public function testReceiveThenSend(): void
    {
        $receiveFuture = async(fn () => $this->channel->receive());
        $sendFuture = async(fn () => $this->channel->send(1));
        self::assertSame(1, $receiveFuture->await());
        self::assertNull($sendFuture->await());
    }

    public function testMultipleSendAndReceive(): void
    {
        $future = async(fn () => $this->channel->receive());
        async(fn () => $this->channel->send(1))->ignore();
        async(fn () => $this->channel->send(2))->ignore();
        self::assertSame(1, $future->await());
        self::assertSame(2, $this->channel->receive());
    }

    public function testCancelReceive(): void
    {
        $this->markTestSkipped('Requires amphp/pipeline-1.0.0-beta.3');

        $deferredCancellation = new DeferredCancellation();
        $future = async(fn () => $this->channel->receive($deferredCancellation->getCancellation()));
        $deferredCancellation->cancel();

        try {
            $future->await();
            self::fail('The receive should have been cancelled');
        } catch (CancelledException) {
            // Expected.
        }

        $future = async(fn () => $this->channel->receive());
        $this->channel->send(1);
        self::assertSame(1, $future->await());
    }
}
