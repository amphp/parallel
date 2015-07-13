<?php
namespace Icicle\Concurrent\Threading;

class Thread extends \Thread
{
    const MSG_DONE = 1;
    const MSG_ERROR = 2;

    private $socket;

    public function initialize($socket)
    {
        $this->socket = $socket;
    }

    public function run()
    {
        echo "TESTING\n";
        sleep(5);

        $this->sendMessage(self::MSG_DONE);
        fclose($this->socket);
    }

    private function sendMessage($message)
    {
        fwrite($this->socket, chr($message));
    }
}
