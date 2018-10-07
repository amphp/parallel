<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskFailure;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\TestCase;
use Amp\Promise;

class TaskFailureTest extends TestCase
{
    /**
     * @expectedException \Amp\Parallel\Worker\TaskException
     * @expectedExceptionMessage Uncaught Exception in worker
     */
    public function testWithException()
    {
        $exception = new \Exception("Message", 1);
        $result = new TaskFailure('a', $exception);
        Promise\wait($result->promise());
    }

    /**
     * @expectedException \Amp\Parallel\Worker\TaskError
     * @expectedExceptionMessage Uncaught Error in worker
     */
    public function testWithError()
    {
        $exception = new \Error("Message", 1);
        $result = new TaskFailure('a', $exception);
        Promise\wait($result->promise());
    }
}
