<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Future;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class CancellingTask implements Task
{
    public function run(Environment $environment, CancellationToken $token): Future
    {
        $deferred = new Deferred;
        $token->subscribe([$deferred, 'error']);
        return $deferred->getFuture();
    }
}
