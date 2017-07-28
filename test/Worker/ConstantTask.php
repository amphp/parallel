<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

class ConstantTask implements Task {
    public function run(Environment $environment) {
        return \defined("AMP_WORKER");
    }
}
