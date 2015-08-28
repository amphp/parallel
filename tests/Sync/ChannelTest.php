<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Stream\DuplexStream;
use Icicle\Tests\Concurrent\TestCase;

class ChannelTest extends TestCase
{
    public function testCreate()
    {
        list($a, $b) = Channel::createSocketPair();

        $this->assertInternalType('resource', $a);
        $this->assertInternalType('resource', $b);
    }

    public function testClose()
    {
        list($a, $b) = Channel::createSocketPair();
        $a = new Channel(new DuplexStream($a));
        $b = new Channel(new DuplexStream($b));

        // Close $a. $b should close on next read...
        $a->close();
        new Coroutine\Coroutine($b->receive());

        Loop\run();

        $this->assertFalse($a->isOpen());
        $this->assertFalse($b->isOpen());
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
}
