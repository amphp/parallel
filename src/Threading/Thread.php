<?php
namespace Icicle\Concurrent\Threading;

/**
 * A thread object that is used by ThreadContext.
 */
class Thread extends \Thread
{
    const MSG_DONE = 1;
    const MSG_ERROR = 2;

    private $socket;
    private $class;

    public function __construct($class)
    {
        $this->class = $class;
    }

    public function initialize($socket)
    {
        $this->socket = $socket;
    }

    public function run()
    {
        $class = $this->class;
        $instance = $class::createThreadInstance();
        $instance->run();

        $this->sendMessage(self::MSG_DONE);
        fclose($this->socket);
    }

    private function sendMessage($message)
    {
        fwrite($this->socket, chr($message));
    }
}
