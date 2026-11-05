<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Api;

use Nyholm\Psr7\Response;
use Sevaske\Taqnyat\Exception\ForbiddenException;
use Sevaske\Taqnyat\Exception\ServerException;

final class SystemTest extends ApiTestCase
{
    public function testOperationalStatusIsRequestedWithoutToken(): void
    {
        $this->http->addResponse(self::json(200, [
            'statusCode' => 200,
            'status' => ['service' => 'none', 'description' => 'All Services are Operational'],
        ]));

        $status = $this->client->system()->status();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.taqnyat.sa/system/status', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));

        self::assertTrue($status->isOperational());
        self::assertFalse($status->isUnderMaintenance());
        self::assertSame(200, $status->getHttpStatusCode());
        self::assertSame('none', $status->getService());
        self::assertSame('All Services are Operational', $status->getDescription());
    }

    public function testMaintenanceIsReturnedNotThrown(): void
    {
        $this->http->addResponse(self::json(503, [
            'statusCode' => 200,
            'status' => ['service' => 'Rest API (HTTPS) ', 'description' => ' Down for Maintenance'],
        ]));

        $status = $this->client->system()->status();

        self::assertFalse($status->isOperational());
        self::assertTrue($status->isUnderMaintenance());
        self::assertSame(503, $status->getHttpStatusCode());
        self::assertSame('Rest API (HTTPS)', $status->getService());
        self::assertSame('Down for Maintenance', $status->getDescription());
    }

    public function testServerErrorWithoutStatusBodyIsThrown(): void
    {
        $this->http->addResponse(new Response(503, [], '<html>Bad gateway</html>'));

        $this->expectException(ServerException::class);

        $this->client->system()->status();
    }

    public function testForbiddenIsThrown(): void
    {
        $this->http->addResponse(new Response(403));

        $this->expectException(ForbiddenException::class);

        $this->client->system()->status();
    }
}
