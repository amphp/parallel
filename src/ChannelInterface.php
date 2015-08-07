<?php
namespace Icicle\Concurrent;

/**
 * Interface for execution context within a thread or fork.
 */
interface ChannelInterface
{
    /**
     * @return \Generator
     *
     * @resolve mixed
     */
    public function receive();

    /**
     * @param mixed $data
     *
     * @return \Generator
     *
     * @resolve int
     */
    public function send($data);
}
