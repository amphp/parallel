<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\Sync\Channel;

/**
 * @template-covariant TResult
 * @template TReceive
 * @template TSend
 * @template-extends Channel<TReceive, TSend>
 */
interface Context extends Channel
{
    /**
     * @return TResult The data returned from the context.
     *
     * @throws ContextException If the context exited with an uncaught exception or non-zero code.
     */
    public function join(?Cancellation $cancellation = null): mixed;
}
