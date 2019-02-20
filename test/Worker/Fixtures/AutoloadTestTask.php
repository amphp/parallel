<?php

namespace Amp\Parallel\Test\Worker\Fixtures;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class AutoloadTestTask implements Task
{
    public function run(Environment $environment): bool
    {
        \class_exists('Amp\\NonExistentClass', true);

        return \defined("AMP_TEST_CUSTOM_AUTOLOADER");
    }
}
