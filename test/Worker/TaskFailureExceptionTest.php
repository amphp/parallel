<?php declare(strict_types=1);

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
            ],
        ];

        $exception = new TaskFailureException('name', 'error_message', 0, 'filename', 1, $trace);

        self::assertSame('name', $exception->getOriginalClassName());
        self::assertSame('error_message', $exception->getOriginalMessage());
        self::assertSame(0, $exception->getOriginalCode());
        self::assertSame('filename', $exception->getOriginalFile());
        self::assertSame(1, $exception->getOriginalLine());
        self::assertSame($trace, $exception->getOriginalTrace());
    }
}
