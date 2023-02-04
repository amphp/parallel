<?php declare(strict_types=1);

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cache\AtomicCache;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;

class CancellingTask implements Task
{
    public function run(Channel $channel, AtomicCache $cache, Cancellation $cancellation): Future
    {
        $deferred = new DeferredFuture;
        $cancellation->subscribe($deferred->error(...));
        return $deferred->getFuture();
    }
}
