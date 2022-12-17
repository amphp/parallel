<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\WorkerException;
use Amp\PHPUnit\AsyncTestCase;

class WorkerExceptionTest extends AsyncTestCase
{
    public function testConstructorShouldBeInstance(): void
    {
        $workerException = new WorkerException('work_exception_message');

        self::assertInstanceOf(WorkerException::class, $workerException);
    }
}
