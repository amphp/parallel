<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\Job;
use Amp\PHPUnit\AsyncTestCase;

class JobTest extends AsyncTestCase
{
    public function testGetJob()
    {
        $task = new Fixtures\TestTask(42);
        $job = new Job($task);
        $this->assertSame($task, $job->getTask());
    }

    public function testUnserializableClass()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Classes implementing Amp\\Parallel\\Worker\\Task must be autoloadable by the Composer autoloader');

        $task = new Fixtures\TestTask(42);
        $job = new Job($task);
        $serialized = \serialize($job);
        $job = \unserialize($serialized, ['allowed_classes' => [Job::class]]);
        $task = $job->getTask();
    }
}
