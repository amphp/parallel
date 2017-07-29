<?php

namespace Amp\Parallel\Worker;

use Amp\Loop;
use Amp\Struct;

class BasicEnvironment implements Environment {
    /** @var array */
    private $data = [];

    /** @var \SplPriorityQueue */
    private $queue;

    /** @var string */
    private $timer;

    public function __construct() {
        $this->queue = new \SplPriorityQueue;

        $this->timer = Loop::repeat(1000, function () {
            $time = \time();
            while (!$this->queue->isEmpty()) {
                list($key, $expiration) = $this->queue->top();

                if (!isset($this->data[$key])) {
                    // Item removed.
                    $this->queue->extract();
                    continue;
                }

                $struct = $this->data[$key];

                if ($struct->expire === 0) {
                    // Item was set again without a TTL.
                    $this->queue->extract();
                    continue;
                }

                if ($struct->expire !== $expiration) {
                    // Expiration changed or TTL updated.
                    $this->queue->extract();
                    continue;
                }

                if ($time < $struct->expire) {
                    // Item at top has not expired, break out of loop.
                    break;
                }

                unset($this->data[$key]);

                $this->queue->extract();
            }

            if ($this->queue->isEmpty()) {
                Loop::disable($this->timer);
            }
        });

        Loop::disable($this->timer);
        Loop::unreference($this->timer);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key): bool {
        return isset($this->data[$key]);
    }

    /**
     * @param string $key
     *
     * @return mixed|null Returns null if the key does not exist.
     */
    public function get(string $key) {
        if (!isset($this->data[$key])) {
            return null;
        }

        $struct = $this->data[$key];

        if ($struct->ttl !== null) {
            $expire = \time() + $struct->ttl;
            if ($struct->expire < $expire) {
                $struct->expire = $expire;
                $this->queue->insert([$key, $struct->expire], -$struct->expire);
            }
        }

        return $struct->data;
    }

    /**
     * @param string $key
     * @param mixed $value Using null for the value deletes the key.
     * @param int $ttl Number of seconds until data is automatically deleted. Use null for unlimited TTL.
     *
     * @throws \Error If the time-to-live is not a positive integer.
     */
    public function set(string $key, $value, int $ttl = null) {
        if ($value === null) {
            $this->delete($key);
            return;
        }

        if ($ttl !== null && $ttl <= 0) {
            throw new \Error("The time-to-live must be a positive integer or null");
        }

        $struct = new class {
            use Struct;
            public $data;
            public $expire = 0;
            public $ttl;
        };

        $struct->data = $value;

        if ($ttl !== null) {
            $struct->ttl = $ttl;
            $struct->expire = \time() + $ttl;
            $this->queue->insert([$key, $struct->expire], -$struct->expire);

            Loop::enable($this->timer);
        }

        $this->data[$key] = $struct;
    }

    /**
     * @param string $key
     */
    public function delete(string $key) {
        unset($this->data[$key]);
    }

    /**
     * Alias of exists().
     *
     * @param $key
     *
     * @return bool
     */
    public function offsetExists($key) {
        return $this->exists($key);
    }

    /**
     * Alias of get().
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key) {
        return $this->get($key);
    }

    /**
     * Alias of set() with $ttl = null.
     *
     * @param string $key
     * @param mixed $value
     */
    public function offsetSet($key, $value) {
        $this->set($key, $value);
    }

    /**
     * Alias of delete().
     *
     * @param string $key
     */
    public function offsetUnset($key) {
        $this->delete($key);
    }

    /**
     * Removes all values.
     */
    public function clear() {
        $this->data = [];

        Loop::disable($this->timer);
        $this->queue = new \SplPriorityQueue;
    }
}
