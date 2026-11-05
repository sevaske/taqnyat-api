<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\Taqnyat\Response\BalanceResponse;

final class BalanceResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unparsableDates(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'wrong format' => ['2031-03-15'];
        yield 'overflowing date' => ['31-02-2031'];
    }

    /**
     * @dataProvider unparsableDates
     *
     * @param mixed $date
     */
    public function testExpiryDateIsNullWhenUnparsable($date): void
    {
        self::assertNull(BalanceResponse::fromArray(['accountExpiryDate' => $date])->getExpiryDate());
    }

    public function testInactiveAccountAndDefaults(): void
    {
        $balance = BalanceResponse::fromArray(['accountStatus' => 'expired']);

        self::assertFalse($balance->isActive());
        self::assertSame('0', $balance->getBalance());
        self::assertSame('0', $balance->getPoints());
        self::assertSame(200, $balance->getStatusCode());
    }

    public function testNumericValuesAreConvertedToStrings(): void
    {
        $balance = BalanceResponse::fromArray(['balance' => 99.5, 'points' => 667]);

        self::assertSame('99.5', $balance->getBalance());
        self::assertSame('667', $balance->getPoints());
    }
}
