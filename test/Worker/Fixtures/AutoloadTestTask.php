<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Cancellation;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class AutoloadTestTask implements Task
{
    public function run(Environment $environment, Cancellation $token): bool
    {
        return \class_exists('CustomAutoloadClass', true);
    }
}
