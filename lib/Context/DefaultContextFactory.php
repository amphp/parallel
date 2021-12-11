<?php

namespace Amp\Parallel\Context;

class DefaultContextFactory implements ContextFactory
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
         *
         * @inheritdoc
         */
        if (Parallel::isSupported()) {
            return new Parallel($script);
        }

        return new Process($script);
    }
}
