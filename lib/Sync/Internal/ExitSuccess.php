<?php declare(strict_types = 1);

namespace Amp\Parallel\Sync\Internal;

class ExitSuccess implements ExitStatus {
    /**
     * @var mixed
     */
    private $result;

    public function __construct($result) {
        $this->result = $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getResult() {
        return $this->result;
    }
}