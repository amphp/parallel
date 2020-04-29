<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\CancellationToken;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class UnserializableResultTask implements Task
{
    public function run(Environment $environment, CancellationToken $token)
    {
        return function () {}; // Anonymous functions are not serializable.
    }
}
