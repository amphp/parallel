<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskFailure;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\TaskException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;

class TaskFailureTest extends AsyncTestCase
{
    public function testWithException()
    {
        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Uncaught Exception in worker');

        $exception = new \Exception("Message", 1);
        $result = new TaskFailure('a', $exception);
        await($result->promise());
    }

    public function testWithError()
    {
        $this->expectException(TaskError::class);
        $this->expectExceptionMessage('Uncaught Error in worker');

        $exception = new \Error("Message", 1);
        $result = new TaskFailure('a', $exception);
        await($result->promise());
    }
}
