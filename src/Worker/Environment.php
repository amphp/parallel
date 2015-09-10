<?php
namespace Icicle\Concurrent\Worker;

use Icicle\Loop;

class Environment
{
    /**
     * @var array
     */
    private $data = [];

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

                if (isset($this->data[$key])) {
                    list( , $expire) = $this->data[$key];

                    if ($time < -$expire) {
                        break;
                    }

                    unset($this->data[$key]);
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
        return isset($this->data[$key]);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        list($value, , $ttl) = $this->data[$key];

        if (0 !== $ttl) {
            $this->data[$key] = [$value, -(time() + $ttl), $ttl];
        }

        return $value;
    }
    
    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     */
    public function set($key, $value, $ttl = 0)
    {
        $ttl = (int) $ttl;
        if (0 > $ttl) {
            $ttl = 0;
        }

        if (0 !== $ttl) {
            $expire = time() + $ttl;
            $this->queue->insert($key, -$expire);

            if (!$this->timer->isPending()) {
                $this->timer->start();
            }
        } else {
            $expire = 0;
        }

        $this->data[$key] = [$value, $expire, $ttl];
    }

    /**
     * @param string $key
     */
    public function delete($key)
    {
        unset($this->data[$key]);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }
}
