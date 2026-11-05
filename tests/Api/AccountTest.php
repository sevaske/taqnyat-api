<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Api;

use Sevaske\Taqnyat\Exception\AuthenticationException;

final class AccountTest extends ApiTestCase
{
    public function testBalance(): void
    {
        $this->http->addResponse(self::json(200, [
            'statusCode' => 200,
            'accountStatus' => 'active',
            'accountExpiryDate' => '15-03-2031',
            'balance' => '100.0000',
            'currency' => 'SAR',
            'points' => '667',
        ]));

        $balance = $this->client->account()->balance();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.taqnyat.sa/account/balance', (string) $request->getUri());
        self::assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Content-Type'));

        self::assertSame(200, $balance->getStatusCode());
        self::assertSame('active', $balance->getAccountStatus());
        self::assertTrue($balance->isActive());
        self::assertSame('100.0000', $balance->getBalance());
        self::assertSame('SAR', $balance->getCurrency());
        self::assertSame('667', $balance->getPoints());
        self::assertSame('15-03-2031', $balance->getAccountExpiryDate());

        $expiry = $balance->getExpiryDate();
        self::assertNotNull($expiry);
        self::assertSame('2031-03-15 00:00:00 Asia/Riyadh', $expiry->format('Y-m-d H:i:s e'));
    }

    public function testBalanceWithWrongToken(): void
    {
        $this->http->addResponse(self::json(401, ['statusCode' => 401, 'message' => 'invalid credentials information.']));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid credentials information.');

        $this->client->account()->balance();
    }
}
