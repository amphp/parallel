<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskFailure;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\TaskException;
use Amp\PHPUnit\AsyncTestCase;

class TaskFailureTest extends AsyncTestCase
{
    public function testWithException(): void
    {
        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Uncaught Exception in worker');

        $exception = new \Exception("Message", 1);
        $result = new TaskFailure('a', $exception);
        $result->getResult();
    }

    public function testWithError(): void
    {
        $this->expectException(TaskError::class);
        $this->expectExceptionMessage('Uncaught Error in worker');

        $exception = new \Error("Message", 1);
        $result = new TaskFailure('a', $exception);
        $result->getResult();
    }
}
