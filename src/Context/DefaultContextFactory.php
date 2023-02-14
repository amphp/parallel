<?php declare(strict_types=1);

namespace Amp\Parallel\Context;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Parallel\Ipc\IpcHub;

final class DefaultContextFactory implements ContextFactory
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly ProcessContextFactory $contextFactory;

    /**
     * @param IpcHub|null $ipcHub Optional IpcHub instance. Global IpcHub instance used if null.
     */
    public function __construct(?IpcHub $ipcHub = null)
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
