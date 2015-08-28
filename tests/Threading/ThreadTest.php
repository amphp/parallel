<?php
namespace Icicle\Tests\Concurrent\Sync;

use Icicle\Concurrent\Threading\Thread;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Tests\Concurrent\TestCase;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends TestCase
{
    public function testIsRunning()
    {
        Coroutine\create(function () {
            $thread = Thread::spawn(function () {
                sleep(1);
            });

            $this->assertTrue($thread->isRunning());
            yield $thread->join();
        })->done();

        Loop\run();
    }

    public function testKill()
    {
        $thread = Thread::spawn(function () {
            sleep(1);
        });

        $thread->kill();
        $this->assertFalse($thread->isRunning());

        Loop\run();
    }

    /**
     * @expectedException \Icicle\Concurrent\Exception\PanicError
     */
    public function testExceptionInThreadPanics()
    {
        Coroutine\create(function () {
            $thread = Thread::spawn(function () {
                throw new \Exception('Exception in thread.');
            });

            yield $thread->join();
        })->done();

        Loop\run();
    }

    public function testJoinWaitsForChild()
    {
        Loop\loop(Loop\create());

        $this->assertRunTimeBetween(function () {
            Coroutine\create(function () {
                $thread = Thread::spawn(function () {
                    sleep(1);
                });

                yield $thread->join();
            })->done();

            Loop\run();
        }, 1, 1.1);
    }
}
