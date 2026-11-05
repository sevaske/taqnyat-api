<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response;

use Sevaske\Taqnyat\Response\Concerns\ReadsData;
use Sevaske\Taqnyat\Support\Text;

/**
 * A sender name registered in your Taqnyat account.
 */
final class Sender
{
    use ReadsData;

    public const STATUS_ACTIVE = 'active';

    public const DESTINATION_KSA = 'KSA';
    public const DESTINATION_INTERNATIONAL = 'international';

    private string $name;

    private string $status;

    private string $destination;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(string $name, string $status, string $destination, array $data = [])
    {
        $this->name = $name;
        $this->status = $status;
        $this->destination = $destination;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::readString($data, 'senderName'),
            self::readString($data, 'status'),
            self::readString($data, 'destination'),
            $data,
        );
    }

    /**
     * Sender name to use in {@see \Sevaske\Taqnyat\Message\SmsMessage}.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Status as reported by Taqnyat, e.g. "active".
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Destination as reported by Taqnyat, e.g. "KSA" or "international".
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    public function isActive(): bool
    {
        return Text::equalsIgnoringCase($this->status, self::STATUS_ACTIVE);
    }

    /**
     * Whether the sender may be used for Saudi numbers.
     */
    public function isLocal(): bool
    {
        return Text::equalsIgnoringCase($this->destination, self::DESTINATION_KSA);
    }

    /**
     * Whether the sender may be used for international numbers.
     */
    public function isInternational(): bool
    {
        return Text::equalsIgnoringCase($this->destination, self::DESTINATION_INTERNATIONAL);
    }

    /**
     * Raw sender data.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
