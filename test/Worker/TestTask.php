<?php declare(strict_types = 1);

namespace Amp\Concurrent\Test\Worker;

use Amp\Concurrent\Worker\{ Environment, Task };

class TestTask implements Task {
    private $returnValue;

    public function __construct($returnValue) {
        $this->returnValue = $returnValue;
    }

    public function run(Environment $environment) {
        return $this->returnValue;
    }
}
