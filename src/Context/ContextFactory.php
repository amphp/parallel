<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\Cancellation;

interface ContextFactory
{
    /**
     * Creates a new execution context.
     *
     * @template TResult
     * @template TReceive
     * @template TSend
     *
     * @param string|non-empty-list<string> $script Path to PHP script or array with first element as path and following
     *     elements as options to the PHP script (e.g.: ['bin/worker', 'ArgumentValue', '--option', 'OptionValue'].
     *
     * @return Context<TResult, TReceive, TSend>
     */
    public function start(string|array $script, ?Cancellation $cancellation = null): Context;
}
