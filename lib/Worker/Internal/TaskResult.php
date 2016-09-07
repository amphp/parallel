<?php declare(strict_types = 1);

namespace Amp\Parallel\Worker\Internal;

use Interop\Async\Awaitable;

abstract class TaskResult {
    /** @var string Task identifier. */
    private $id;
    
    /**
     * @param string $id Task identifier.
     */
    public function __construct(string $id) {
        $this->id = $id;
    }
    
    /**
     * @return string Task identifier.
     */
    public function getId(): string {
        return $this->id;
    }
    
    /**
     * @return \Interop\Async\Awaitable<mixed> Resolved with the task result or failure reason.
     */
    abstract public function getAwaitable(): Awaitable;
}