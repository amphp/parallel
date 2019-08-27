<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\TaskError;
use Amp\PHPUnit\AsyncTestCase;

class TaskErrorTest extends AsyncTestCase
{
    public function testGetWorkerTrace()
    {
        $taskError = new TaskError('name', 'error_message', 'error_message_trace');

        $this->assertSame('error_message_trace', $taskError->getWorkerTrace());
    }
}
