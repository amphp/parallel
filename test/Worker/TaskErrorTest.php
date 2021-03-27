<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\TaskError;
use Amp\PHPUnit\AsyncTestCase;

class TaskErrorTest extends AsyncTestCase
{
    public function testGetWorkerTrace(): void
    {
        $taskError = new TaskError('name', 'error_message', 'error_message_trace');

        self::assertSame('error_message_trace', $taskError->getWorkerTrace());
    }
}
