<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Api;

use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sevaske\Taqnyat\Client;
use Sevaske\Taqnyat\ClientOptions;

abstract class ApiTestCase extends TestCase
{
    protected MockClient $http;

    protected Client $client;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->http = new MockClient($factory);
        $this->client = new Client('secret-token', ClientOptions::create()
            ->withHttpClient($this->http)
            ->withRequestFactory($factory)
            ->withStreamFactory($factory));
    }

    /**
     * @param array<string, mixed> $body
     */
    protected static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }
}
