<?php
namespace Icicle\Concurrent\Worker;

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

use Icicle\Concurrent\Sync\Channel;
use Icicle\Concurrent\Sync\ChannelInterface;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Stream\ReadableStream;
use Icicle\Socket\Stream\WritableStream;

function run(ChannelInterface $channel)
{
    try {
        while (true) {
            $task = (yield $channel->receive());

            // Shutdown request
            if ($task === 1) {
                break;
            }

            if (!($task instanceof TaskInterface)) {
                throw new \Exception('Invalid message.');
            }

            yield $channel->send(yield $task->run());
        }
    } finally {
        $channel->close();
    }
}

// Redirect all output written using echo, print, printf, etc. to STDERR.
ob_start(function ($data) {
    $written = fwrite(STDERR, $data);
    return '';
}, 1);

$coroutine = new Coroutine(
    run(new Channel(new ReadableStream(STDIN), new WritableStream(STDOUT)))
);
$coroutine->done();

Loop\run();
