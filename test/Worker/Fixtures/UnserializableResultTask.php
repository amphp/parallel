<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cancellation;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class UnserializableResultTask implements Task
{
    public function run(Environment $environment, Cancellation $token): mixed
    {
        return function () {
            // Anonymous functions are not serializable.
        };
    }
}
