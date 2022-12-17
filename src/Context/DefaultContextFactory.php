<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

final class DefaultContextFactory implements ContextFactory
{
    /**
     * @template TResult
     * @template TReceive
     * @template TSend
     *
     * @param string|string[] $script
     *
     * @return Context<TResult, TReceive, TSend>
     *
     * @throws ContextException
     */
    public function start(string|array $script): Context
    {
        return ProcessContext::start($script);
    }
}
