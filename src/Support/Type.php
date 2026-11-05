<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Support;

/**
 * @internal
 */
final class Type
{
    /**
     * Human-readable type of a value for error messages, e.g. "array" or "App\Foo".
     *
     * @param mixed $value
     */
    public static function describe($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }

        return gettype($value);
    }
}
