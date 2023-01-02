<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\Parallel\Ipc\IpcHub;

final class DefaultContextFactory implements ContextFactory
{
    /**
     * @param IpcHub|null $ipcHub Optional IpcHub instance. Global IpcHub instance used if null.
     */
    public function __construct(private readonly ?IpcHub $ipcHub = null)
    {
    }

    /**
     * @template TResult
     * @template TReceive
     * @template TSend
     *
     * @param string|list<string> $script
     *
     * @return Context<TResult, TReceive, TSend>
     *
     * @throws ContextException
     */
    public function start(string|array $script, ?Cancellation $cancellation = null): Context
    {
        return ProcessContext::start($script, cancellation: $cancellation, ipcHub: $this->ipcHub);
    }
}
