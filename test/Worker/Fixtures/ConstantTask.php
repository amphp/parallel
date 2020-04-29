<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\CancellationToken;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class ConstantTask implements Task
{
    public function run(Environment $environment, CancellationToken $token): bool
    {
        return \defined("AMP_WORKER");
    }
}
