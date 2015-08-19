<?php
namespace Icicle\Concurrent\Sync;

class ExitSuccess implements ExitStatusInterface
{
    /**
     * @var mixed
     */
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->result;
    }
}