<?php

namespace Amp\Parallel\Context;

use Revolt\EventLoop\Loop;

const LOOP_FACTORY_IDENTIFIER = ContextFactory::class;

/**
 * @param string|string[] $script Path to PHP script or array with first element as path and following elements options
 *     to the PHP script (e.g.: ['bin/worker', 'Option1Value', 'Option2Value'].
 *
 * @return Context
 */
function create(string|array $script): Context
{
    return factory()->create($script);
}

/**
 * Gets or sets the global context factory.
 *
 * @param ContextFactory|null $factory
 *
 * @return ContextFactory
 */
function factory(?ContextFactory $factory = null): ContextFactory
{
    if ($factory === null) {
        $factory = Loop::getState(LOOP_FACTORY_IDENTIFIER);
        if ($factory) {
            return $factory;
        }

        $factory = new DefaultContextFactory;
    }
    Loop::setState(LOOP_FACTORY_IDENTIFIER, $factory);
    return $factory;
}
