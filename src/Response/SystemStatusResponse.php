<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response;

use Sevaske\Taqnyat\Response\Concerns\ReadsData;

/**
 * Result of GET /system/status.
 *
 * A maintenance response (HTTP 503) is returned as an instance with isOperational() === false
 * instead of throwing, so checking the status never needs a try/catch for that case.
 */
final class SystemStatusResponse
{
    use ReadsData;

    private int $httpStatusCode;

    private string $service;

    private string $description;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(int $httpStatusCode, string $service, string $description, array $data = [])
    {
        $this->httpStatusCode = $httpStatusCode;
        $this->service = $service;
        $this->description = $description;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data           decoded JSON response body
     * @param int                     $httpStatusCode HTTP status of the response (200 or 503)
     */
    public static function fromArray(array $data, int $httpStatusCode = 200): self
    {
        $status = $data['status'] ?? [];

        if (!is_array($status)) {
            $status = [];
        }

        return new self(
            $httpStatusCode,
            self::readString($status, 'service'),
            self::readString($status, 'description'),
            $data,
        );
    }

    /**
     * True when the SMS service is up (HTTP 200), false during maintenance (HTTP 503).
     */
    public function isOperational(): bool
    {
        return $this->httpStatusCode >= 200 && $this->httpStatusCode < 300;
    }

    public function isUnderMaintenance(): bool
    {
        return !$this->isOperational();
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Affected service, e.g. "none" or "Rest API (HTTPS)".
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * Human-readable status, e.g. "All Services are Operational" or "Down for Maintenance".
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Raw decoded response body.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
