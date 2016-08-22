<?php declare(strict_types = 1);

namespace Amp\Concurrent\Threading\Internal;

/**
 * @internal
 */
class Storage extends \Threaded {
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function get() {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function set($value) {
        $this->value = $value;
    }
}
