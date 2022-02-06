<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

class AutoloadTestTask implements Task
{
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): bool
    {
        return \class_exists('CustomAutoloadClass', true);
    }
}
