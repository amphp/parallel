<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\DefaultWorkerFactory;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\TestCase;

class DefaultWorkerFactoryTest extends TestCase
{
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Invalid environment class name 'Invalid'
     */
    public function testInvalidClassName()
    {
        $factory = new DefaultWorkerFactory("Invalid");
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage does not implement 'Amp\Parallel\Worker\Environment'
     */
    public function testNonEnvironmentClassName()
    {
        $factory = new DefaultWorkerFactory(DefaultWorkerFactory::class);
    }

    public function testCreate()
    {
        $factory = new DefaultWorkerFactory;

        $this->assertInstanceOf(Worker::class, $factory->create());
    }
}
