<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\Taqnyat\Response\SendSmsResponse;

final class SendSmsResponseTest extends TestCase
{
    public function testParsesDocumentedResponse(): void
    {
        $data = [
            'statusCode' => 201,
            'messageId' => '5829452722',
            'cost' => '0.1500',
            'currency' => 'SAR',
            'totalCount' => 1,
            'msgLength' => 1,
            'accepted' => '[966500000000,]',
            'rejected' => '[]',
        ];

        $response = SendSmsResponse::fromArray($data);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('5829452722', $response->getMessageId());
        self::assertSame('0.1500', $response->getCost());
        self::assertSame('SAR', $response->getCurrency());
        self::assertSame(1, $response->getTotalCount());
        self::assertSame(1, $response->getMessageLength());
        self::assertSame(['966500000000'], $response->getAccepted());
        self::assertSame([], $response->getRejected());
        self::assertFalse($response->hasRejected());
        self::assertSame($data, $response->toArray());
    }

    /**
     * @return iterable<string, array{mixed, list<string>}>
     */
    public static function recipientLists(): iterable
    {
        yield 'string with trailing comma' => ['[966500000000,966511111111,]', ['966500000000', '966511111111']];
        yield 'string with spaces and quotes' => ['[ "966500000000" , 966511111111 ]', ['966500000000', '966511111111']];
        yield 'empty string list' => ['[]', []];
        yield 'empty string' => ['', []];
        yield 'json array of ints' => [[966500000000, 966511111111], ['966500000000', '966511111111']];
        yield 'json array of strings' => [['966500000000'], ['966500000000']];
        yield 'single int' => [966500000000, ['966500000000']];
        yield 'null' => [null, []];
    }

    /**
     * @dataProvider recipientLists
     *
     * @param mixed        $raw
     * @param list<string> $expected
     */
    public function testParsesRecipientLists($raw, array $expected): void
    {
        $response = SendSmsResponse::fromArray(['accepted' => $raw, 'rejected' => $raw]);

        self::assertSame($expected, $response->getAccepted());
        self::assertSame($expected, $response->getRejected());
        self::assertSame($expected !== [], $response->hasRejected());
    }

    public function testToleratesNumericTypes(): void
    {
        $response = SendSmsResponse::fromArray(['messageId' => 5829452722, 'cost' => 0.15, 'totalCount' => '2']);

        self::assertSame('5829452722', $response->getMessageId());
        self::assertSame('0.15', $response->getCost());
        self::assertSame(2, $response->getTotalCount());
    }
}
