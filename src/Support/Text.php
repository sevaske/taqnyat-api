<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Support;

/**
 * @internal
 */
final class Text
{
    public static function equalsIgnoringCase(string $first, string $second): bool
    {
        return strcasecmp($first, $second) === 0;
    }

    public static function startsWith(string $text, string $prefix): bool
    {
        return substr($text, 0, strlen($prefix)) === $prefix;
    }

    /**
     * Returns the trimmed string, or null when the value is not a string or is blank.
     *
     * @param mixed $value
     */
    public static function nonEmptyOrNull($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }
}
