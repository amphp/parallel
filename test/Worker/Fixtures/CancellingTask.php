<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class CancellingTask implements Task
{
    public function run(Environment $environment, Cancellation $token): Future
    {
        $deferred = new DeferredFuture;
        $token->subscribe(\Closure::fromCallable([$deferred, 'error']));
        return $deferred->getFuture();
    }
}
