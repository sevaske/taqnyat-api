<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Thrown when the HTTP request could not be completed (DNS failure, timeout, connection refused, ...).
 *
 * No response from Taqnyat was received, so it is unknown whether the message was accepted.
 */
class TransportException extends \RuntimeException implements TaqnyatException
{
    private RequestInterface $request;

    public function __construct(string $message, RequestInterface $request, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->request = $request;
    }

    public static function fromClientException(ClientExceptionInterface $exception, RequestInterface $request): self
    {
        $message = 'Failed to send request to Taqnyat API: ' . $exception->getMessage();

        return new self($message, $request, $exception);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
