<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\TaskException;
use Amp\PHPUnit\AsyncTestCase;

class TaskExceptionTest extends AsyncTestCase
{
    public function testGetName()
    {
        $taskException = new TaskException('task_exception_name', 'task_exception_message', 'task_trace_message');

        $this->assertSame('task_exception_name', $taskException->getName());
    }

    public function testGetWorkerTrace()
    {
        $taskException = new TaskException('task_exception_name', 'task_exception_message', 'task_trace_message');

        $this->assertSame('task_trace_message', $taskException->getWorkerTrace());
    }
}
