<?php
namespace Icicle\Concurrent;

interface ProcessInterface extends ContextInterface
{
    /**
     * @return int PID of process.
     */
    public function getPid();

    /**
     * @param int $signo
     *
     * @throws \Icicle\Concurrent\Exception\StatusError
     */
    public function signal($signo);
}
