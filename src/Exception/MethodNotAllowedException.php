<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when the Taqnyat API responds with HTTP 405 Method Not Allowed.
 */
class MethodNotAllowedException extends ApiException {}
