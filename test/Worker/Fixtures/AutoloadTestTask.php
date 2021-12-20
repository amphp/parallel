<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Worker\Task;

class AutoloadTestTask implements Task
{
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): bool
    {
        return \class_exists('CustomAutoloadClass', true);
    }
}
