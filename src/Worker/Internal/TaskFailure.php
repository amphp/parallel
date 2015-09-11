<?php
namespace Icicle\Concurrent\Worker\Internal;

use Icicle\Concurrent\Exception\TaskException;

class TaskFailure
{
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

    public function __construct(\Exception $exception)
    {
        $this->type = get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();
    }

    /**
     * {@inheritdoc}
     */
    public function getException()
    {
        return new TaskException(
            sprintf('Uncaught exception in worker of type "%s" with message "%s"', $this->type, $this->message),
            $this->code,
            $this->trace
        );
    }
}