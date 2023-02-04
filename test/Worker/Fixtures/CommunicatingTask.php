<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\AtomicCache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

class CommunicatingTask implements Task
{
    public function run(Channel $channel, AtomicCache $cache, Cancellation $cancellation): string
    {
        $channel->send('test');
        return $channel->receive($cancellation);
    }
}
