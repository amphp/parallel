<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Process;
use Amp\PHPUnit\TestCase;
use Amp\Promise;

class ProcessTest extends TestCase {
    public function testBasicProcess() {
        $process = new Process([
            __DIR__ . "/test-process.php",
            "Test"
        ]);
        $process->start();
        $this->assertSame("Test", Promise\wait($process->join()));
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No string provided
     */
    public function testFailingProcess() {
        $process = new Process(__DIR__ . "/test-process.php");
        $process->start();
        Promise\wait($process->join());
    }

    /**
     * @expectedException \Amp\Parallel\Sync\PanicError
     * @expectedExceptionMessage No script found at 'test-process.php'
     */
    public function testInvalidScriptPath() {
        $process = new Process("test-process.php");
        $process->start();
        Promise\wait($process->join());
    }
}
