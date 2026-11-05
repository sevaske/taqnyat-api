<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when a successful response cannot be understood (invalid JSON, missing fields, ...).
 */
class UnexpectedResponseException extends ApiException
{
    public static function invalidJson(ResponseInterface $response, RequestInterface $request): self
    {
        return new self(
            'Taqnyat API returned a successful response with an invalid JSON body.',
            $response->getStatusCode(),
            [],
            $response,
            $request,
        );
    }
}
