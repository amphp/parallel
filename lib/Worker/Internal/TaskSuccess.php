<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\Promise;
use Amp\Success;

/** @internal */
class TaskSuccess extends TaskResult
{
    /** @var mixed Result of task. */
    private $result;

    public function __construct(string $id, $result)
    {
        parent::__construct($id);
        $this->result = $result;
    }

    public function promise(): Promise
    {
        return new Success($this->result);
    }
}
