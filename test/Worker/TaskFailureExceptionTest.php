<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\TaskFailureException;
use Amp\PHPUnit\AsyncTestCase;

class TaskFailureExceptionTest extends AsyncTestCase
{
    public function testOriginalMethods(): void
    {
        $trace = [
            [
                'function' => 'error_message_trace',
                'file' => 'file-name.php',
                'line' => 1,
                'args' => [],
            ]
        ];

        $exception = new TaskFailureException('name', 'error_message', 0, $trace);

        $this->assertSame('name', $exception->getOriginalClassName());
        $this->assertSame('error_message', $exception->getOriginalMessage());
        $this->assertSame(0, $exception->getOriginalCode());
        $this->assertSame($trace, $exception->getOriginalTrace());
    }
}
