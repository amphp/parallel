<?php

namespace Amp\Parallel\Context\Internal;

/**
 * @internal
 * @template TValue
 * @template-implements ExitResult<TValue>
 */
final class ExitSuccess implements ExitResult
{
    /** @var mixed */
    private mixed $result;

    /**
     * @param TValue $result
     */
    public function __construct(mixed $result)
    {
        $this->result = $result;
    }

    /**
     * @return TValue
     */
    public function getResult(): mixed
    {
        return $this->result;
    }
}
