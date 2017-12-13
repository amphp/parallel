<?php

namespace Amp\Parallel\Sync;

class ExitFailure implements ExitResult {
    /** @var string */
    private $type;

    /** @var string */
    private $message;

    /** @var int|string */
    private $code;

    /** @var array */
    private $trace;

    public function __construct(\Throwable $exception) {
        $this->type = \get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();
    }

    /**
     * {@inheritdoc}
     */
    public function getResult() {
        throw new PanicError(
            $this->type,
            \sprintf(
                'Uncaught %s in execution context with message "%s" and code "%s"',
                $this->type,
                $this->message,
                $this->code
            ),
            $this->trace
        );
    }
}
