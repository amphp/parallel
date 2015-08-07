<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Sync\Channel;
use Icicle\Coroutine;
use Icicle\Loop;
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
        $a = new Channel($a);
        $b = new Channel($b);

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
            $a = new Channel($a);
            $b = new Channel($b);

            yield $a->send('hello');
            $data = (yield $b->receive());
            $this->assertEquals('hello', $data);
        });

        Loop\run();
    }

    /**
     * @group threading
     */
    public function testThreadTransfer()
    {
        list($a, $b) = Channel::createSocketPair();
        $a = new Channel($a);
        $b = new Channel($b);

        $thread = \Thread::from(function () {
            $a = $this->a;
        });

        $thread->a; // <-- Transfer channel $a to the thread
        $thread->start(PTHREADS_INHERIT_INI);
        $thread->join();
    }
}
