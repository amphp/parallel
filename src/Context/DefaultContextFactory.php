<?php

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
        /**
         * Creates a thread if ext-parallel is installed, otherwise creates a child process.
         */
        if (ParallelContext::isSupported()) {
            return ParallelContext::start($script);
        }

        return ProcessContext::start($script);
    }
}
