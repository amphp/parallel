<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Worker\AutoloadingWorkerFactory;
use Amp\PHPUnit\TestCase;

class AutoloadingWorkerFactoryTest extends TestCase
{
    public function testAutoloading()
    {
        $factory = new AutoloadingWorkerFactory(__DIR__ . '/Fixtures/custom-autoloader.php');

        Loop::run(function () use ($factory) {
            $worker = $factory->create();

            $this->assertTrue(yield $worker->enqueue(new Fixtures\AutoloadTestTask));

            yield $worker->shutdown();
        });
    }

    public function testInvalidAutoloaderPath()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('No file found at autoload path given');

        $factory = new AutoloadingWorkerFactory(__DIR__ . '/Fixtures/not-found.php');
    }

    public function testInvalidClassName()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Invalid environment class name 'Invalid'");

        $factory = new AutoloadingWorkerFactory(__DIR__ . '/Fixtures/custom-autoloader.php', "Invalid");
    }

    public function testNonEnvironmentClassName()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("does not implement 'Amp\Parallel\Worker\Environment'");

        $factory = new AutoloadingWorkerFactory(
            __DIR__ . '/Fixtures/custom-autoloader.php',
            AutoloadingWorkerFactory::class
        );
    }
}
