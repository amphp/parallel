<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\DefaultWorkerFactory;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\AsyncTestCase;

class DefaultWorkerFactoryTest extends AsyncTestCase
{
    public function testInvalidClassName()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Invalid environment class name 'Invalid'");

        $factory = new DefaultWorkerFactory("Invalid");
    }

    public function testNonEnvironmentClassName()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("does not implement 'Amp\\Parallel\\Worker\\Environment'");

        $factory = new DefaultWorkerFactory(DefaultWorkerFactory::class);
    }

    public function testCreate()
    {
        $factory = new DefaultWorkerFactory;

        $this->assertInstanceOf(Worker::class, $factory->create());
    }
}
