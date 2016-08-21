<?php

namespace Amp\Concurrent\Worker\Internal;

use Amp\Success;
use Interop\Async\Awaitable;

class TaskSuccess implements TaskResult {
    /** @var string */
    private $id;
    
    /** @var mixed Result of task. */
    private $result;
    
    public function __construct(string $id, $result) {
        $this->id = $id;
        $this->result = $result;
    }
    
    public function getId(): string {
        return $this->id;
    }
    
    public function getAwaitable(): Awaitable {
        return new Success($this->result);
    }
}
