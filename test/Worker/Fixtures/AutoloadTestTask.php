<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class AutoloadTestTask implements Task
{
    public function run(Environment $environment): bool
    {
        return \class_exists('CustomAutoloadClass', true);
    }
}
