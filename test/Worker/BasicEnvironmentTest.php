<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Delayed;
use Amp\Loop;
use Amp\Parallel\Worker\BasicEnvironment;
use Amp\PHPUnit\TestCase;

class BasicEnvironmentTest extends TestCase {
    public function testBasicOperations() {
        $environment = new BasicEnvironment;
        $key = "key";

        $this->assertFalse($environment->exists($key));
        $this->assertNull($environment->get($key));

        $environment->set($key, 1);
        $this->assertTrue($environment->exists($key));
        $this->assertSame(1, $environment->get($key));

        $environment->set($key, 2);
        $this->assertSame(2, $environment->get($key));

        $environment->delete($key);
        $this->assertFalse($environment->exists($key));
        $this->assertNull($environment->get($key));
    }

    public function testArrayAccess() {
        $environment = new BasicEnvironment;
        $key = "key";

        $this->assertFalse(isset($environment[$key]));
        $this->assertNull($environment[$key]);

        $environment[$key] = 1;
        $this->assertTrue(isset($environment[$key]));
        $this->assertSame(1, $environment[$key]);

        $environment[$key] = 2;
        $this->assertSame(2, $environment[$key]);

        unset($environment[$key]);
        $this->assertFalse(isset($environment[$key]));
        $this->assertNull($environment[$key]);
    }

    public function testClear() {
        $environment = new BasicEnvironment;

        $environment->set("key1", 1);
        $environment->set("key2", 2);

        $environment->clear();

        $this->assertFalse($environment->exists("key1"));
        $this->assertFalse($environment->exists("key2"));
    }

    public function testTtl() {
        Loop::run(function () {
            $environment = new BasicEnvironment;
            $key = "key";

            $environment->set($key, 1, 2);

            yield new Delayed(3000);

            $this->assertFalse($environment->exists($key));
        });
    }

    /**
     * @depends testTtl
     */
    public function testRemovingTtl() {
        Loop::run(function () {
            $environment = new BasicEnvironment;
            $key = "key";

            $environment->set($key, 1, 1);

            $environment->set($key, 2);

            yield new Delayed(2000);

            $this->assertTrue($environment->exists($key));
            $this->assertSame(2, $environment->get($key));
        });
    }
}
