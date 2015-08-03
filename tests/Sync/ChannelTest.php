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
        list($a, $b) = Channel::create();

        $a->send('hello')->then(function () use ($b) {
            return new Coroutine\Coroutine($b->receive());
        })->done(function ($data) {
            $this->assertEquals('hello', $data);
        });

        Loop\run();
    }
}
