<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Loop;
use Icicle\Coroutine;

class ChannelTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        list($a, $b) = Channel::create();

        $this->assertInstanceOf(Channel::class, $a);
        $this->assertInstanceOf(Channel::class, $b);
    }

    public function testClose()
    {
        list($a, $b) = Channel::create();

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
            list($a, $b) = Channel::create();

            yield $a->send('hello');
            $data = (yield $b->receive());
            $this->assertEquals('hello', $data);
        });

        Loop\run();
    }
}
