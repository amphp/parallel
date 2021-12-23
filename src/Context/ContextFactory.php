<?php

namespace Amp\Parallel\Context;

interface ContextFactory
{
    /**
     * Creates a new execution context.
     *
     * @template TResult
     * @template TReceive
     * @template TSend
     *
     * @param string|string[] $script Path to PHP script or array with first element as path and following elements
     *     options to the PHP script (e.g.: ['bin/worker', 'Option1Value', 'Option2Value'].
     *
     * @return Context<TResult, TReceive, TSend>
     */
    public function create(string|array $script): Context;
}
