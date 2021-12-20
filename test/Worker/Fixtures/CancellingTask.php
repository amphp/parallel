<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Worker\Task;

class CancellingTask implements Task
{
    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): Future
    {
        $deferred = new DeferredFuture;
        $cancellation->subscribe(\Closure::fromCallable([$deferred, 'error']));
        return $deferred->getFuture();
    }
}
