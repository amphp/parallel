<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class UnserializableResultTask implements Task
{
    public function run(Environment $environment)
    {
        return function () {}; // Anonymous functions are not serializable.
    }
}
