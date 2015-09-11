<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Loop;

class Environment implements \ArrayAccess, \Countable
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var array
     */
    private $ttl = [];

    /**
     * @var array
     */
    private $expire = [];

    /**
     * @var \SplPriorityQueue
     */
    private $queue;

    /**
     * @var \Icicle\Loop\Events\TimerInterface
     */
    private $timer;

    public function __construct()
    {
        $this->queue = new \SplPriorityQueue();

        $this->timer = Loop\periodic(1, function () {
            $time = time();
            while (!$this->queue->isEmpty()) {
                $key = $this->queue->top();

                if (isset($this->expire[$key])) {
                    if ($time <= $this->expire[$key]) {
                        break;
                    }

                    $this->delete($key);
                }

                $this->queue->extract();
            }

            if ($this->queue->isEmpty()) {
                $this->timer->stop();
            }
        });

        $this->timer->stop();
        $this->timer->unreference();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists($key)
    {
        return isset($this->data[(string) $key]);
    }

    /**
     * @param string $key
     *
     * @return mixed|null Returns null if the key does not exist.
     */
    public function get($key)
    {
        $key = (string) $key;

        if (isset($this->ttl[$key]) && 0 !== $this->ttl[$key]) {
            $this->expire[$key] = time() + $this->ttl[$key];
            $this->queue->insert($key, -$this->expire[$key]);
        }

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
    
    /**
     * @param string $key
     * @param mixed $value Using null for the value deletes the key.
     * @param int $ttl
     */
    public function set($key, $value, $ttl = 0)
    {
        $key = (string) $key;

        if (null === $value) {
            $this->delete($key);
            return;
        }

        $ttl = (int) $ttl;
        if (0 > $ttl) {
            $ttl = 0;
        }

        if (0 !== $ttl) {
            $this->ttl[$key] = $ttl;
            $this->expire[$key] = time() + $ttl;
            $this->queue->insert($key, -$this->expire[$key]);

            if (!$this->timer->isPending()) {
                $this->timer->start();
            }
        } else {
            unset($this->expire[$key]);
            unset($this->ttl[$key]);
        }

        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     */
    public function delete($key)
    {
        $key = (string) $key;

        unset($this->data[$key]);
        unset($this->expire[$key]);
        unset($this->ttl[$key]);
    }

    /**
     * Alias of exists().
     *
     * @param $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    /**
     * Alias of get().
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Alias of set() with $ttl = 0.
     *
     * @param string $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Alias of delete().
     *
     * @param string $key
     */
    public function offsetUnset($key)
    {
        $this->delete($key);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Removes all values.
     */
    public function clear()
    {
        $this->data = [];
        $this->expire = [];
        $this->ttl = [];

        $this->timer->stop();
        $this->queue = new \SplPriorityQueue();
    }
}
