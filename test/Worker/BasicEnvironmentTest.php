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

    public function testSetWithNullValue() {
        $environment = new BasicEnvironment;
        $key = "key";
        $environment->set($key, null);

        $this->assertNull($environment->get($key));
    }

    /**
     * @expectedException        \Error
     * @expectedExceptionMessage The time-to-live must be a positive integer or null
     */
    public function testSetShouleThrowError() {
        $environment = new BasicEnvironment;
        $key = "key";
        $environment->set($key, 1, 0);
    }

    public function testArrayAccess() {
        $environment = new BasicEnvironment;
        $key = "key";

        $this->assertArrayNotHasKey($key, $environment);
        $this->assertNull($environment[$key]);

        $environment[$key] = 1;
        $this->assertArrayHasKey($key, $environment);
        $this->assertSame(1, $environment[$key]);

        $environment[$key] = 2;
        $this->assertSame(2, $environment[$key]);

        unset($environment[$key]);
        $this->assertArrayNotHasKey($key, $environment);
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

    public function testShorteningTtl() {
        Loop::run(function () {
            $environment = new BasicEnvironment;
            $key = "key";

            $environment->set($key, 1, 10);
            $environment->set($key, 1, 1);

            yield new Delayed(2000);

            $this->assertFalse($environment->exists($key));
        });
    }

    public function testLengtheningTtl() {
        Loop::run(function () {
            $environment = new BasicEnvironment;
            $key = "key";

            $environment->set($key, 1, 1);
            $environment->set($key, 1, 3);

            yield new Delayed(2000);

            $this->assertTrue($environment->exists($key));

            yield new Delayed(1100);

            $this->assertFalse($environment->exists($key));
        });
    }

    public function testAccessExtendsTtl() {
        Loop::run(function () {
            $environment = new BasicEnvironment;
            $key1 = "key1";
            $key2 = "key2";

            $environment->set($key1, 1, 2);
            $environment->set($key2, 2, 2);

            yield new Delayed(1000);

            $this->assertSame(1, $environment->get($key1));
            $this->assertTrue($environment->exists($key2));

            yield new Delayed(1500);

            $this->assertTrue($environment->exists($key1));
            $this->assertFalse($environment->exists($key2));
        });
    }
}
