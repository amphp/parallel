<?php
namespace Icicle\Concurrent\Exception;

class PanicError extends Error
{
    /**
     * @var array Stack trace of the panic.
     */
    private $panicTrace;

    /**
     * Creates a new panic error.
     *
     * @param string $message The panic message.
     * @param int    $code    The panic code.
     * @param array  $trace   The panic stack trace.
     */
    public function __construct($message = '', $code = 0, array $trace = [])
    {
        parent::__construct($message, $code);
        $this->panicTrace = $trace;
    }

    /**
     * Gets the stack trace at the point the panic occurred.
     *
     * @return array
     */
    public function getPanicTrace()
    {
        return $this->panicTrace;
    }

    /**
     * Gets the panic stack trace as a string.
     *
     * @return string
     */
    public function getPanicTraceAsString()
    {
        foreach ($this->panicTrace as $id => $scope) {
            $string .= sprintf("%d# %s(%d): %s%s%s()\n",
                $id,
                $scope['file'],
                $scope['line'],
                isset($scope['class']) ? $scope['class'] : '',
                isset($scope['type']) ? $scope['type'] : '',
                $scope['function']);
        }

        return $string;
    }
}
