<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\JobTaskRun;
use Amp\PHPUnit\AsyncTestCase;

class JobTest extends AsyncTestCase
{
    public function testGetJob(): void
    {
        $task = new Fixtures\TestTask(42);
        $job = new JobTaskRun($task);
        self::assertSame($task, $job->getTask());
    }

    public function testUnserializableClass(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Classes implementing Amp\\Parallel\\Worker\\Task must be autoloadable by the Composer autoloader');

        $task = new Fixtures\TestTask(42);
        $job = new JobTaskRun($task);
        $serialized = \serialize($job);
        $job = \unserialize($serialized, ['allowed_classes' => [JobTaskRun::class]]);
        $task = $job->getTask();
    }
}
