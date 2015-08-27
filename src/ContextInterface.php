<?php
namespace Icicle\Concurrent;

interface ContextInterface extends ChannelInterface
{
    /**
     * @return bool
     */
    public function isRunning();

    /**
     * Starts the execution context.
     */
    public function start();

    /**
     * Immediately kills the context.
     */
    public function kill();

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve mixed
     */
    public function join();
}
