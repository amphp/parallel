<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerException;
use Amp\PHPUnit\AsyncTestCase;

class WorkerExceptionTest extends AsyncTestCase
{
    public function testConstructorShouldBeInstance()
    {
        $workerException = new WorkerException('work_exception_message');

        $this->assertInstanceOf(WorkerException::class, $workerException);
    }
}
