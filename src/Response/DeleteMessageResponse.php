<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response;

use Sevaske\Taqnyat\Response\Concerns\ReadsData;

/**
 * Successful result of DELETE /v1/messages/delete.
 */
final class DeleteMessageResponse
{
    use ReadsData;

    private int $statusCode;

    private string $message;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(int $statusCode, string $message, array $data = [])
    {
        $this->statusCode = $statusCode;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON response body
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::readInt($data, 'statusCode', 201),
            self::readString($data, 'message'),
            $data,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Human-readable result, e.g. "Deleted successfully".
     */
    public function getMessage(): string
    {
        return $this->message;
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
