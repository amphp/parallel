<?php

namespace Amp\Parallel\Context;

final class DefaultContextFactory implements ContextFactory
{
    /**
     * @template TValue
     *
     * @param string|array $script
     *
     * @return Context<TValue>
     */
    public function create(string|array $script): Context
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
