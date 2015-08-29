<?php
namespace Icicle\Concurrent\Threading\Internal;

/**
 * @internal
 */
class Storage extends \Threaded
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function set($value)
    {
        $this->value = $value;
    }
}
