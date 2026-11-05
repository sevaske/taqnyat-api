<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 422 Unprocessable Entity (the request data is invalid).
 */
class ValidationException extends ApiException {}
