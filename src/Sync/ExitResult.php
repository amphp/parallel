<?php

namespace Amp\Parallel\Sync;

/**
 * @template TValue
 */
interface ExitResult
{
    /**
     * @return TValue Return value of the callable given to the execution context.
     *
     * @throws ContextPanicError If the context exited with an uncaught exception.
     */
    public function getResult(): mixed;
}
