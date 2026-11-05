<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response\Concerns;

/**
 * Tolerant readers for decoded API payloads.
 *
 * @internal
 */
trait ReadsData
{
    /**
     * @param array<array-key, mixed> $data
     */
    private static function readString(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readInt(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
