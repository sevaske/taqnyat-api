<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Api;

use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\ValidationException;
use Sevaske\Taqnyat\Response\Sender;

final class MessagesManagementTest extends ApiTestCase
{
    public function testDeleteScheduledMessage(): void
    {
        $this->http->addResponse(self::json(201, ['statusCode' => 201, 'message' => 'Deleted successfully']));

        $response = $this->client->messages()->delete(3242424);

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('https://api.taqnyat.sa/v1/messages/delete', (string) $request->getUri());
        self::assertSame('Bearer secret-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"deleteId":3242424}', (string) $request->getBody());

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Deleted successfully', $response->getMessage());
        self::assertSame(['statusCode' => 201, 'message' => 'Deleted successfully'], $response->toArray());
    }

    public function testDeleteWithUnknownIdThrowsValidationException(): void
    {
        $this->http->addResponse(self::json(422, ['statusCode' => 422, 'message' => 'Invalid deleteId']));

        try {
            $this->client->messages()->delete(1);
            self::fail('Exception expected');
        } catch (ValidationException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('Invalid deleteId', $e->getMessage());
        }
    }

    public function testDeleteRejectsNonPositiveIdWithoutRequest(): void
    {
        try {
            $this->client->messages()->delete(0);
            self::fail('Exception expected');
        } catch (InvalidArgumentException $e) {
            self::assertSame([], $this->http->getRequests());
        }
    }

    public function testSenders(): void
    {
        $this->http->addResponse(self::json(201, [
            'statusCode' => 201,
            'senders' => [
                ['senderName' => 'sender name 1', 'status' => 'active', 'destination' => 'KSA'],
                ['senderName' => 'sender name 2', 'status' => 'active', 'destination' => 'international'],
                ['senderName' => 'Pending', 'status' => 'pending', 'destination' => 'KSA'],
                'garbage',
            ],
        ]));

        $senders = $this->client->messages()->senders();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.taqnyat.sa/v1/messages/senders', (string) $request->getUri());

        self::assertCount(3, $senders);
        self::assertSame(['sender name 1', 'sender name 2', 'Pending'], $senders->getNames());
        self::assertCount(2, $senders->getActive());
        self::assertContainsOnlyInstancesOf(Sender::class, iterator_to_array($senders));

        $local = $senders->find('SENDER NAME 1');
        self::assertNotNull($local);
        self::assertSame('sender name 1', $local->getName());
        self::assertTrue($local->isActive());
        self::assertTrue($local->isLocal());
        self::assertFalse($local->isInternational());

        $international = $senders->getSenders()[1];
        self::assertTrue($international->isInternational());
        self::assertSame('international', $international->getDestination());

        $pending = $senders->find('Pending');
        self::assertNotNull($pending);
        self::assertFalse($pending->isActive());
        self::assertSame('pending', $pending->getStatus());

        self::assertTrue($senders->has('Pending'));
        self::assertFalse($senders->has('Unknown'));
        self::assertNull($senders->find('Unknown'));
    }

    public function testEmptySenders(): void
    {
        $this->http->addResponse(self::json(201, ['statusCode' => 201]));

        $senders = $this->client->messages()->senders();

        self::assertCount(0, $senders);
        self::assertSame([], $senders->getSenders());
    }
}
