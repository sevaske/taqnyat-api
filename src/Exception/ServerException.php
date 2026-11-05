<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 5xx (Taqnyat server error or maintenance).
 */
class ServerException extends ApiException {}
