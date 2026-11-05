<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sevaske\Taqnyat\Support\Text;

/**
 * Thrown when the Taqnyat API responds with an error.
 *
 * Use {@see ApiException::fromResponse()} to get the most specific subclass for a given status code.
 */
class ApiException extends \RuntimeException implements TaqnyatException
{
    /**
     * Specific exception classes for known status codes. All 5xx codes map to {@see ServerException}.
     */
    private const EXCEPTION_CLASS_BY_STATUS_CODE = [
        400 => BadRequestException::class,
        401 => AuthenticationException::class,
        403 => ForbiddenException::class,
        405 => MethodNotAllowedException::class,
        422 => ValidationException::class,
        429 => RateLimitException::class,
    ];

    /**
     * Body fields that may contain a human-readable error message, in order of preference.
     */
    private const MESSAGE_FIELDS = ['message', 'error', 'description'];

    private int $statusCode;

    /**
     * @var array<array-key, mixed>
     */
    private array $responseData;

    private ?ResponseInterface $response;

    private ?RequestInterface $request;

    /**
     * @param array<array-key, mixed> $responseData
     */
    public function __construct(
        string $message,
        int $statusCode,
        array $responseData = [],
        ?ResponseInterface $response = null,
        ?RequestInterface $request = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->responseData = $responseData;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * Creates the most specific exception for the given error response.
     *
     * @param array<array-key, mixed>|null $data decoded JSON body, if any
     */
    public static function fromResponse(
        ResponseInterface $response,
        ?array $data = null,
        ?RequestInterface $request = null
    ): self {
        $data = $data ?? [];

        $statusCode = self::resolveStatusCode($response, $data);
        $message = self::resolveMessage($response, $data, $statusCode);
        $exceptionClass = self::exceptionClassFor($statusCode);

        return new $exceptionClass($message, $statusCode, $data, $response, $request);
    }

    /**
     * HTTP status code of the error (taken from the response body when the API reports it there).
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Decoded JSON body of the error response (empty when the body was not JSON).
     *
     * @return array<array-key, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * @return class-string<ApiException>
     */
    private static function exceptionClassFor(int $statusCode): string
    {
        if (isset(self::EXCEPTION_CLASS_BY_STATUS_CODE[$statusCode])) {
            return self::EXCEPTION_CLASS_BY_STATUS_CODE[$statusCode];
        }

        if ($statusCode >= 500) {
            return ServerException::class;
        }

        return self::class;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function resolveStatusCode(ResponseInterface $response, array $data): int
    {
        $httpStatusCode = $response->getStatusCode();

        if ($httpStatusCode >= 400) {
            return $httpStatusCode;
        }

        // The HTTP status claims success, but the body reports an error: trust the body.
        $statusCodeInBody = $data['statusCode'] ?? null;

        if (is_numeric($statusCodeInBody)) {
            return (int) $statusCodeInBody;
        }

        return $httpStatusCode;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function resolveMessage(ResponseInterface $response, array $data, int $statusCode): string
    {
        $messageFromBody = self::findMessageInBody($data);

        if ($messageFromBody !== null) {
            return $messageFromBody;
        }

        return self::defaultMessage($response, $statusCode);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function findMessageInBody(array $data): ?string
    {
        foreach (self::MESSAGE_FIELDS as $field) {
            $message = Text::nonEmptyOrNull($data[$field] ?? null);

            if ($message !== null) {
                return $message;
            }
        }

        // Maintenance responses: {"statusCode": 503, "status": {"service": "...", "description": "..."}}
        $status = $data['status'] ?? null;

        if (is_array($status)) {
            return Text::nonEmptyOrNull($status['description'] ?? null);
        }

        return null;
    }

    /**
     * "Taqnyat API error (HTTP 403): Forbidden"
     */
    private static function defaultMessage(ResponseInterface $response, int $statusCode): string
    {
        $message = sprintf('Taqnyat API error (HTTP %d)', $statusCode);
        $reasonPhrase = $response->getReasonPhrase();

        if ($reasonPhrase !== '') {
            $message .= ': ' . $reasonPhrase;
        }

        return $message;
    }
}
