<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\TaskFailureError;
use Amp\PHPUnit\AsyncTestCase;

class TaskFailureErrorTest extends AsyncTestCase
{
    public function testOriginalMethods(): void
    {
        $trace = [
            [
                'function' => 'error_message_trace',
                'file' => 'file-name.php',
                'line' => 1,
                'args' => [],
            ],
        ];

        $exception = new TaskFailureError('name', 'error_message', 0, $trace);

        self::assertSame('name', $exception->getOriginalClassName());
        self::assertSame('error_message', $exception->getOriginalMessage());
        self::assertSame(0, $exception->getOriginalCode());
        self::assertSame($trace, $exception->getOriginalTrace());
    }
}
