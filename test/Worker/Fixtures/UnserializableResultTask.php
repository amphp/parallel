<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

class UnserializableResultTask implements Task
{
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
    {
        return function () {
            // Anonymous functions are not serializable.
        };
    }
}
