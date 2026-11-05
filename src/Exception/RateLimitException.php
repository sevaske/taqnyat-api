<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 429 Too Many Requests.
 */
class RateLimitException extends ApiException {}
