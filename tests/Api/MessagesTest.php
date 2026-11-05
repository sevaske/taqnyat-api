<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Api;

use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Sevaske\Taqnyat\Client;
use Sevaske\Taqnyat\ClientOptions;
use Sevaske\Taqnyat\Exception\ApiException;
use Sevaske\Taqnyat\Exception\AuthenticationException;
use Sevaske\Taqnyat\Exception\BadRequestException;
use Sevaske\Taqnyat\Exception\ForbiddenException;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\MethodNotAllowedException;
use Sevaske\Taqnyat\Exception\RateLimitException;
use Sevaske\Taqnyat\Exception\ServerException;
use Sevaske\Taqnyat\Exception\TaqnyatException;
use Sevaske\Taqnyat\Exception\TransportException;
use Sevaske\Taqnyat\Exception\UnexpectedResponseException;
use Sevaske\Taqnyat\Exception\ValidationException;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Template\Template;

final class MessagesTest extends ApiTestCase
{
    private const SUCCESS_BODY = [
        'statusCode' => 201,
        'messageId' => '5829452722',
        'cost' => '0.1500',
        'currency' => 'SAR',
        'totalCount' => 2,
        'msgLength' => 1,
        'accepted' => '[966500000000,966511111111,]',
        'rejected' => '[]',
    ];


    public function testSendsMessage(): void
    {
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));

        $response = $this->client->messages()->send(
            SmsMessage::create('Taqnyat.sa', ['+966500000000', '966511111111'], 'مرحبا Hello'),
        );

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.taqnyat.sa/v1/messages', (string) $request->getUri());
        self::assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertStringStartsWith('sevaske-taqnyat-php/', $request->getHeaderLine('User-Agent'));
        self::assertSame([
            'recipients' => ['966500000000', '966511111111'],
            'body' => 'مرحبا Hello',
            'sender' => 'Taqnyat.sa',
        ], self::decodeBody($request));
        self::assertStringContainsString('مرحبا', (string) $request->getBody(), 'Unicode must not be escaped');

        self::assertSame('5829452722', $response->getMessageId());
        self::assertSame('0.1500', $response->getCost());
        self::assertSame(['966500000000', '966511111111'], $response->getAccepted());
    }

    public function testClientSendShortcut(): void
    {
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));

        $response = $this->client->send(SmsMessage::create('S', '966500000000', 'x'));

        self::assertSame('5829452722', $response->getMessageId());
    }

    public function testSendsScheduledMessageInConfiguredTimezone(): void
    {
        $factory = new Psr17Factory();
        $client = new Client('t', ClientOptions::create()
            ->withHttpClient($this->http)
            ->withRequestFactory($factory)
            ->withStreamFactory($factory)
            ->withTimezone('UTC')
            ->withBaseUri('https://proxy.example.com/taqnyat/'));
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));

        $client->messages()->send(
            SmsMessage::create('S', '966500000000', 'x')
                ->scheduleAt(new \DateTimeImmutable('2030-09-30 14:26', new \DateTimeZone('Asia/Riyadh')))
                ->withDeleteId(3242424),
        );

        $request = $this->lastRequest();
        self::assertSame('https://proxy.example.com/taqnyat/v1/messages', (string) $request->getUri());
        $payload = self::decodeBody($request);
        self::assertSame('2030-09-30T11:26', $payload['scheduledDatetime']);
        self::assertSame(3242424, $payload['deleteId']);
    }

    public function testSendText(): void
    {
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));

        $this->client->messages()->sendText('S', '966500000000', 'Hi');

        self::assertSame('Hi', self::decodeBody($this->lastRequest())['body']);
    }

    public function testSendTemplate(): void
    {
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));

        $this->client->messages()->sendTemplate('S', '966500000000', new Template('Code {code}'), ['code' => 1234]);
        self::assertSame('Code 1234', self::decodeBody($this->lastRequest())['body']);

        $this->client->messages()->sendTemplate('S', '966500000000', 'Hi {name}', ['name' => 'Sara']);
        self::assertSame('Hi Sara', self::decodeBody($this->lastRequest())['body']);
    }

    public function testTemplateErrorsHappenBeforeAnyRequest(): void
    {
        try {
            $this->client->messages()->sendTemplate('S', '966500000000', 'Code {code}', []);
            self::fail('Exception expected');
        } catch (InvalidArgumentException $e) {
            self::assertSame([], $this->http->getRequests());
        }
    }

    /**
     * @return iterable<string, array{int, array<string, mixed>|string, class-string<ApiException>, string}>
     */
    public static function errorResponses(): iterable
    {
        yield '400 sender not active' => [400, ['statusCode' => 400, 'message' => 'Sender Name not active .'], BadRequestException::class, 'Sender Name not active .'];
        yield '401 wrong token' => [401, ['statusCode' => 401, 'message' => 'invalid credentials information.'], AuthenticationException::class, 'invalid credentials information.'];
        yield '403 html body' => [403, '<html>Forbidden</html>', ForbiddenException::class, 'Taqnyat API error (HTTP 403): Forbidden'];
        yield '405' => [405, ['statusCode' => 405, 'message' => 'Method Not Allowed.'], MethodNotAllowedException::class, 'Method Not Allowed.'];
        yield '422' => [422, ['statusCode' => 422, 'message' => 'Invalid data'], ValidationException::class, 'Invalid data'];
        yield '429' => [429, '', RateLimitException::class, 'Taqnyat API error (HTTP 429): Too Many Requests'];
        yield '503 maintenance' => [503, ['statusCode' => 503, 'status' => ['service' => 'Rest API', 'description' => 'Down for Maintenance']], ServerException::class, 'Down for Maintenance'];
        yield '418 unknown' => [418, ['message' => 'teapot'], ApiException::class, 'teapot'];
        yield 'error status in 200 body' => [200, ['statusCode' => 400, 'message' => 'Sender Name not active .'], BadRequestException::class, 'Sender Name not active .'];
    }

    /**
     * @dataProvider errorResponses
     *
     * @param array<string, mixed>|string    $body
     * @param class-string<ApiException>     $expectedClass
     */
    public function testMapsErrorResponses(int $status, $body, string $expectedClass, string $expectedMessage): void
    {
        $this->http->addResponse(is_array($body) ? self::json($status, $body) : new Response($status, [], $body));

        try {
            $this->client->messages()->sendText('S', '966500000000', 'x');
            self::fail('Exception expected');
        } catch (ApiException $e) {
            self::assertSame($expectedClass, get_class($e));
            self::assertInstanceOf(TaqnyatException::class, $e);
            self::assertSame($expectedMessage, $e->getMessage());
            self::assertSame(is_array($body) ? $body : [], $e->getResponseData());
            self::assertNotNull($e->getResponse());
            self::assertNotNull($e->getRequest());
            self::assertSame($e->getStatusCode(), $e->getCode());
        }
    }

    public function testThrowsOnInvalidJsonInSuccessfulResponse(): void
    {
        $this->http->addResponse(new Response(201, [], 'not json'));

        $this->expectException(UnexpectedResponseException::class);

        $this->client->messages()->sendText('S', '966500000000', 'x');
    }

    public function testWrapsNetworkErrors(): void
    {
        $request = (new Psr17Factory())->createRequest('POST', 'https://api.taqnyat.sa/v1/messages');
        $this->http->addException(new NetworkException('Connection refused', $request));

        try {
            $this->client->messages()->sendText('S', '966500000000', 'x');
            self::fail('Exception expected');
        } catch (TransportException $e) {
            self::assertStringContainsString('Connection refused', $e->getMessage());
            self::assertInstanceOf(NetworkException::class, $e->getPrevious());
            self::assertSame('POST', $e->getRequest()->getMethod());
        }
    }

    public function testRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Client('  ', ClientOptions::create()->withHttpClient($this->http));
    }

    public function testRejectsInvalidOptions(): void
    {
        try {
            ClientOptions::create()->withTimezone('Mars/Olympus');
            self::fail('Exception expected');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Mars/Olympus', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        ClientOptions::create()->withBaseUri('ftp://example.com');
    }

    public function testCustomUserAgent(): void
    {
        $factory = new Psr17Factory();
        $client = new Client('t', ClientOptions::create()
            ->withHttpClient($this->http)
            ->withRequestFactory($factory)
            ->withStreamFactory($factory)
            ->withUserAgent('MyApp/2.0'));
        $this->http->addResponse(self::json(201, self::SUCCESS_BODY));

        $client->messages()->sendText('S', '966500000000', 'x');

        self::assertSame('MyApp/2.0', $this->lastRequest()->getHeaderLine('User-Agent'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeBody(RequestInterface $request): array
    {
        $data = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }
}
