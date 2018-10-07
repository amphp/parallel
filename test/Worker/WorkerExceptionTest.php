<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerException;
use Amp\PHPUnit\TestCase;

class WorkerExceptionTest extends TestCase
{
    public function testConstructorShouldBeInstance()
    {
        $workerException = new WorkerException('work_exception_message');

        $this->assertInstanceOf(WorkerException::class, $workerException);
    }
}
