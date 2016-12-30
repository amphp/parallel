<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\{ Environment, Task };

class TestTask implements Task {
    private $returnValue;

    public function __construct($returnValue) {
        $this->returnValue = $returnValue;
    }

    public function run(Environment $environment) {
        return $this->returnValue;
    }
}
