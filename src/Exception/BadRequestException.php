<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 400 Bad Request (e.g. the sender name is not active).
 */
class BadRequestException extends ApiException {}
