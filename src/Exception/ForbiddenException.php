<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 403 Forbidden (contact Taqnyat support for details).
 */
class ForbiddenException extends ApiException {}
