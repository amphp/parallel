<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Promise;

class CancellingTask implements Task
{
    public function run(Environment $environment, CancellationToken $token): Promise
    {
        $deferred = new Deferred;
        $token->subscribe([$deferred, 'fail']);
        return $deferred->promise();
    }
}
