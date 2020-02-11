<?php

namespace Amp\Parallel\Sync;

/**
 * @param \Throwable $exception
 *
 * @return string[] Serializable array of strings representing the exception backtrace including function arguments.
 */
function flattenThrowableBacktrace(\Throwable $exception): array
{
    $output = [];
    $counter = 0;
    $trace = $exception->getTrace();

    foreach ($trace as $call) {
        if (isset($call['class'])) {
            $name = $call['class'] . $call['type'] . $call['function'];
        } else {
            $name = $call['function'];
        }

        $args = \implode(', ', \array_map(__NAMESPACE__ . '\\flattenArgument', $call['args']));

        $output[] = \sprintf(
            '#%d %s(%d): %s(%s)',
            $counter++,
            $call['file'] ?? '[internal function]',
            $call['line'] ?? 0,
            $name,
            $args
        );
    }

    return $output;
}

/**
 * @param mixed $value
 *
 * @return string Serializable string representation of $value for backtraces.
 */
function flattenArgument($value): string
{
    if ($value instanceof \Closure) {
        $closureReflection = new \ReflectionFunction($value);
        return \sprintf(
            'Closure(%s:%s)',
            $closureReflection->getFileName(),
            $closureReflection->getStartLine()
        );
    }

    if (\is_object($value)) {
        return \sprintf('Object(%s)', \get_class($value));
    }

    if (\is_array($value)) {
        return 'Array([' . \implode(', ', \array_map(__FUNCTION__, $value)) . '])';
    }

    if (\is_resource($value)) {
        return \sprintf('Resource(%s)', \get_resource_type($value));
    }

    if (\is_string($value)) {
        return '"' . $value . '"';
    }

    if (\is_null($value)) {
        return 'null';
    }

    if (\is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
}
