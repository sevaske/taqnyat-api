<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Exception;

/**
 * Thrown when invalid input is passed to the library (before any HTTP request is made).
 */
class InvalidArgumentException extends \InvalidArgumentException implements TaqnyatException {}
