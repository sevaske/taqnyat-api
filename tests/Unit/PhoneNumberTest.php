<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Message\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string|int, string}>
     */
    public static function validNumbers(): iterable
    {
        yield 'plain' => ['966500000000', '966500000000'];
        yield 'integer' => [966500000000, '966500000000'];
        yield 'plus prefix' => ['+966500000000', '966500000000'];
        yield 'double zero prefix' => ['00966500000000', '966500000000'];
        yield 'formatted' => ['+966 (50) 000-00.00', '966500000000'];
        yield 'surrounding spaces' => ['  966500000000 ', '966500000000'];
        yield 'other country' => ['+971501234567', '971501234567'];
    }

    /**
     * @dataProvider validNumbers
     *
     * @param string|int $input
     */
    public function testNormalizesValidNumbers($input, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::from($input)->toString());
        self::assertSame($expected, (string) PhoneNumber::from($input));
        self::assertTrue(PhoneNumber::isValid($input));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['96650abc0000'];
        yield 'too short' => ['9665000'];
        yield 'too long' => ['9665000000000000'];
        yield 'local leading zero' => ['0500000000'];
        yield 'plus and double zero' => ['+00966500000000'];
        yield 'array' => [['966500000000']];
        yield 'null' => [null];
    }

    /**
     * @dataProvider invalidNumbers
     *
     * @param mixed $input
     */
    public function testRejectsInvalidNumbers($input): void
    {
        self::assertFalse(PhoneNumber::isValid($input));

        $this->expectException(InvalidArgumentException::class);
        PhoneNumber::from($input);
    }

    public function testFromReturnsSameInstance(): void
    {
        $number = PhoneNumber::from('966500000000');

        self::assertSame($number, PhoneNumber::from($number));
    }

    public function testEquals(): void
    {
        self::assertTrue(PhoneNumber::from('+966500000000')->equals(PhoneNumber::from('00966500000000')));
        self::assertFalse(PhoneNumber::from('966500000000')->equals(PhoneNumber::from('966511111111')));
    }
}
