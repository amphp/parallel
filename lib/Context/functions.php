<?php

namespace Amp\Parallel\Context;

use Amp\Promise;

/**
 * @param string|string[] $script
 *
 * @return Context
 */
function create($script): Context
{
    if (Parallel::isSupported()) {
        return new Parallel($script);
    }

    return new Process($script);
}

/**
 * @param string|string[] $script
 *
 * @return Promise<Context>
 */
function run($script): Promise
{
    if (Parallel::isSupported()) {
        return Parallel::run($script);
    }

    return Process::run($script);
}
