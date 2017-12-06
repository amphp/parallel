<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Process;
use Amp\PHPUnit\TestCase;
use Amp\Promise;

class ProcessTest extends TestCase {
    public function testBasicProcess() {
        $process = new Process([
            dirname(__DIR__) . "/bin/process",
            "-sTest"
        ]);
        $process->start();
        $this->assertSame("Test", Promise\wait($process->join()));
    }
}
