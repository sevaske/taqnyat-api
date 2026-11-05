<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Message;

use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Support\Text;
use Sevaske\Taqnyat\Support\Type;

/**
 * A recipient phone number in the international format expected by Taqnyat
 * (country code + subscriber number, digits only, without "+" or "00").
 *
 * Common human formatting is normalized automatically:
 *  "+966 50 000-0000", "00966500000000", "(966) 500000000" => "966500000000"
 */
final class PhoneNumber
{
    /**
     * The shortest number we accept: a country code plus a short subscriber number.
     */
    private const MIN_LENGTH = 8;

    /**
     * The longest number allowed by the E.164 standard.
     */
    private const MAX_LENGTH = 15;

    /**
     * Characters people use to format phone numbers: spaces, dashes, dots, slashes and parentheses.
     */
    private const FORMATTING_CHARACTERS_PATTERN = '/[\s\-().\/]+/u';

    /**
     * Digits only. A country code never starts with zero.
     */
    private const DIGITS_WITHOUT_LEADING_ZERO_PATTERN = '/^[1-9][0-9]*$/';

    private string $number;

    private function __construct(string $number)
    {
        $this->number = $number;
    }

    /**
     * @param string|int|self|mixed $number
     *
     * @throws InvalidArgumentException when the value is not a valid international number
     */
    public static function from($number): self
    {
        if ($number instanceof self) {
            return $number;
        }

        if (is_int($number)) {
            $number = (string) $number;
        }

        if (!is_string($number)) {
            $message = sprintf('Phone number must be a string or an integer, %s given.', Type::describe($number));

            throw new InvalidArgumentException($message);
        }

        return new self(self::normalize($number));
    }

    /**
     * Returns true when the value can be converted into a valid phone number.
     *
     * @param mixed $number
     */
    public static function isValid($number): bool
    {
        try {
            self::from($number);

            return true;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    public function toString(): string
    {
        return $this->number;
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number;
    }

    public function __toString(): string
    {
        return $this->number;
    }

    private static function normalize(string $rawNumber): string
    {
        $number = self::removeFormatting($rawNumber);
        $number = self::removeInternationalPrefix($number);

        if (!self::isWellFormed($number)) {
            $message = sprintf(
                'Invalid phone number "%s": expected international format with country code, e.g. 966500000000.',
                $rawNumber,
            );

            throw new InvalidArgumentException($message);
        }

        return $number;
    }

    /**
     * "+966 (50) 000-0000" => "+966500000000"
     */
    private static function removeFormatting(string $number): string
    {
        $withoutFormatting = preg_replace(self::FORMATTING_CHARACTERS_PATTERN, '', trim($number));

        // preg_replace() returns null for invalid UTF-8; such a value is not a valid number anyway.
        return $withoutFormatting ?? '';
    }

    /**
     * "+966500000000" and "00966500000000" => "966500000000"
     */
    private static function removeInternationalPrefix(string $number): string
    {
        if (Text::startsWith($number, '+')) {
            return substr($number, 1);
        }

        if (Text::startsWith($number, '00')) {
            return substr($number, 2);
        }

        return $number;
    }

    private static function isWellFormed(string $number): bool
    {
        return self::hasOnlyDigitsWithoutLeadingZero($number)
            && self::hasValidLength($number);
    }

    private static function hasOnlyDigitsWithoutLeadingZero(string $number): bool
    {
        return preg_match(self::DIGITS_WITHOUT_LEADING_ZERO_PATTERN, $number) === 1;
    }

    private static function hasValidLength(string $number): bool
    {
        $length = strlen($number);

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }
}
