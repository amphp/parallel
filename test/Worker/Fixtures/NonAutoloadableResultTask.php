<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

class NonAutoloadableResultTask implements Task
{
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
    {
        require __DIR__ . "/non-autoloadable-class.php";
        return new NonAutoloadableClass;
    }
}
