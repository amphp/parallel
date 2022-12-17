<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskSuccess;
use Amp\PHPUnit\AsyncTestCase;

class TaskSuccessTest extends AsyncTestCase
{
    public function testGetId(): void
    {
        $id = 'a';
        $result = new TaskSuccess($id, 1);
        self::assertSame($id, $result->getId());
    }

    public function testPromise(): void
    {
        $value = 1;
        $result = new TaskSuccess('a', $value);
        self::assertSame($value, $result->getResult());
    }
}
