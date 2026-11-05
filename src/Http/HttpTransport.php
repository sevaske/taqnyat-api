<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\Taqnyat\Exception\ApiException;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\TransportException;
use Sevaske\Taqnyat\Exception\UnexpectedResponseException;

/**
 * Low-level JSON-over-HTTP transport for the Taqnyat REST API.
 *
 * @internal not covered by the backward compatibility promise
 */
final class HttpTransport
{
    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private string $baseUri;

    private string $token;

    private string $userAgent;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri,
        string $token,
        string $userAgent
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = rtrim($baseUri, '/');
        $this->token = $token;
        $this->userAgent = $userAgent;
    }

    /**
     * Sends an authenticated request and returns the decoded JSON body of a successful response.
     *
     * @param array<string, mixed>|null $payload JSON body
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException          on network failure
     * @throws ApiException                when the API returns an error
     * @throws UnexpectedResponseException when a successful response is not valid JSON
     */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        $request = $this->createRequest($method, $path);
        $request = $this->withAuthorization($request);

        if ($payload !== null) {
            $request = $this->withJsonBody($request, $payload);
        }

        return $this->send($request);
    }

    /**
     * Same as {@see request()}, but without the bearer token (for public endpoints).
     *
     * @return array<array-key, mixed>
     *
     * @throws TransportException
     * @throws ApiException
     * @throws UnexpectedResponseException
     */
    public function requestWithoutToken(string $method, string $path): array
    {
        $request = $this->createRequest($method, $path);

        return $this->send($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function send(RequestInterface $request): array
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw TransportException::fromClientException($e, $request);
        }

        $data = $this->decodeJson($response);

        if ($this->isErrorResponse($response, $data)) {
            throw ApiException::fromResponse($response, $data, $request);
        }

        if ($data === null) {
            throw UnexpectedResponseException::invalidJson($response, $request);
        }

        return $data;
    }

    private function createRequest(string $method, string $path): RequestInterface
    {
        $uri = $this->baseUri . '/' . ltrim($path, '/');

        return $this->requestFactory
            ->createRequest($method, $uri)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);
    }

    private function withAuthorization(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function withJsonBody(RequestInterface $request, array $payload): RequestInterface
    {
        $json = $this->encodeJson($payload);
        $body = $this->streamFactory->createStream($json);

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload): string
    {
        // Keep Arabic text and URLs readable in the request body.
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        try {
            return json_encode($payload, $flags);
        } catch (\JsonException $e) {
            $message = 'Failed to encode request payload as JSON: ' . $e->getMessage();

            throw new InvalidArgumentException($message, 0, $e);
        }
    }

    /**
     * @return array<array-key, mixed>|null null when the body is empty or not a JSON object
     */
    private function decodeJson(ResponseInterface $response): ?array
    {
        $body = trim((string) $response->getBody());

        if ($body === '') {
            return null;
        }

        // Large numeric IDs stay strings instead of losing precision as floats.
        $flags = JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR;

        try {
            $data = json_decode($body, true, 512, $flags);
        } catch (\JsonException $e) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed>|null $data
     */
    private function isErrorResponse(ResponseInterface $response, ?array $data): bool
    {
        if ($response->getStatusCode() >= 400) {
            return true;
        }

        // Defensive: the API may report an error in the body while using a 2xx HTTP status.
        $statusCodeInBody = $data['statusCode'] ?? null;

        return is_numeric($statusCodeInBody) && (int) $statusCodeInBody >= 400;
    }
}
