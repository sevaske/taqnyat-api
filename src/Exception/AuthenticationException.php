<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 401 Unauthorized (invalid or missing bearer token).
 */
class AuthenticationException extends ApiException {}
