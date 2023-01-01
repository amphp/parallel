<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskFailure;
use Amp\Parallel\Worker\TaskFailureError;
use Amp\Parallel\Worker\TaskFailureException;
use Amp\PHPUnit\AsyncTestCase;

class TaskFailureTest extends AsyncTestCase
{
    public function testWithException(): void
    {
        $this->expectException(TaskFailureException::class);
        $this->expectExceptionMessage('Exception thrown in context');

        $exception = new \Exception("Message", 1);
        $result = new TaskFailure('a', $exception);
        $result->getResult();
    }

    public function testWithError(): void
    {
        $this->expectException(TaskFailureError::class);
        $this->expectExceptionMessage('Error thrown in context');

        $exception = new \Error("Message", 1);
        $result = new TaskFailure('a', $exception);
        $result->getResult();
    }
}
