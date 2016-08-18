<?php

namespace Amp\Concurrent\Sync\Internal;

use Amp\Concurrent\PanicError;

class ExitFailure implements ExitStatus {
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $message;

    /**
     * @var int
     */
    private $code;

    /**
     * @var array
     */
    private $trace;

    public function __construct(\Throwable $exception) {
        $this->type = get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();
    }

    /**
     * {@inheritdoc}
     */
    public function getResult() {
        throw new PanicError(
            \sprintf(
                'Uncaught exception in execution context of type "%s" with message "%s"',
                $this->type,
                $this->message
            ),
            $this->code,
            $this->trace
        );
    }
}