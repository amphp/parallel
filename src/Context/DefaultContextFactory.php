<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Parallel\Ipc\IpcHub;
use Amp\Parallel\Ipc\LocalIpcHub;

final class DefaultContextFactory implements ContextFactory
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly ProcessContextFactory $contextFactory;

    /**
     * @param IpcHub $ipcHub Optional IpcHub instance.
     */
    public function __construct(IpcHub $ipcHub = new LocalIpcHub())
    {
        $this->contextFactory = new ProcessContextFactory(ipcHub: $ipcHub);
    }

    /**
     * @param string|non-empty-list<string> $script
     *
     * @throws ContextException
     */
    public function start(string|array $script, ?Cancellation $cancellation = null): Context
    {
        return $this->contextFactory->start($script, $cancellation);
    }
}
