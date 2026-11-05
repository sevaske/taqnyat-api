<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response;

use Sevaske\Taqnyat\Response\Concerns\ReadsData;

/**
 * Successful result of POST /v1/messages.
 *
 * Note that a successful response does not guarantee that every recipient was accepted:
 * check {@see SendSmsResponse::getRejected()} / {@see SendSmsResponse::hasRejected()}.
 */
final class SendSmsResponse
{
    use ReadsData;

    /**
     * Whitespace and square brackets around a list like "[966500000000,]".
     */
    private const LIST_WRAPPING_CHARACTERS = " \t\n\r\0\x0B[]";

    /**
     * Whitespace and quotes around a single number.
     */
    private const NUMBER_WRAPPING_CHARACTERS = " \t\n\r\0\x0B\"'";

    private int $statusCode;

    private string $messageId;

    private string $cost;

    private string $currency;

    private int $totalCount;

    private int $messageLength;

    /**
     * @var list<string>
     */
    private array $accepted;

    /**
     * @var list<string>
     */
    private array $rejected;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param list<string>            $accepted
     * @param list<string>            $rejected
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        int $statusCode,
        string $messageId,
        string $cost,
        string $currency,
        int $totalCount,
        int $messageLength,
        array $accepted,
        array $rejected,
        array $data = []
    ) {
        $this->statusCode = $statusCode;
        $this->messageId = $messageId;
        $this->cost = $cost;
        $this->currency = $currency;
        $this->totalCount = $totalCount;
        $this->messageLength = $messageLength;
        $this->accepted = $accepted;
        $this->rejected = $rejected;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON response body
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::readInt($data, 'statusCode', 201),
            self::readString($data, 'messageId'),
            self::readString($data, 'cost', '0'),
            self::readString($data, 'currency'),
            self::readInt($data, 'totalCount'),
            self::readInt($data, 'msgLength'),
            self::parseNumberList($data['accepted'] ?? null),
            self::parseNumberList($data['rejected'] ?? null),
            $data,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Taqnyat message ID.
     */
    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * Message cost as a decimal string (e.g. "0.1500"), kept as string to avoid float rounding.
     */
    public function getCost(): string
    {
        return $this->cost;
    }

    /**
     * Currency of the cost, e.g. "SAR".
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Total count of accepted recipients.
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Message length as reported by Taqnyat.
     */
    public function getMessageLength(): int
    {
        return $this->messageLength;
    }

    /**
     * Accepted phone numbers.
     *
     * @return list<string>
     */
    public function getAccepted(): array
    {
        return $this->accepted;
    }

    /**
     * Rejected phone numbers.
     *
     * @return list<string>
     */
    public function getRejected(): array
    {
        return $this->rejected;
    }

    public function hasRejected(): bool
    {
        return $this->rejected !== [];
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

    /**
     * Parses a recipient list. Taqnyat returns it as a string like "[966500000000,966511111111,]",
     * but real JSON arrays are supported as well.
     *
     * @param mixed $value
     *
     * @return list<string>
     */
    private static function parseNumberList($value): array
    {
        $numbers = [];

        foreach (self::splitNumberList($value) as $item) {
            $number = self::cleanNumber($item);

            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * @param mixed $value
     *
     * @return array<array-key, mixed>
     */
    private static function splitNumberList($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $withoutBrackets = trim($value, self::LIST_WRAPPING_CHARACTERS);

            return explode(',', $withoutBrackets);
        }

        if (is_int($value) || is_float($value)) {
            return [$value];
        }

        return [];
    }

    /**
     * @param mixed $item
     */
    private static function cleanNumber($item): ?string
    {
        if (is_float($item)) {
            // Numbers that do not fit into an integer are decoded as floats: print them without exponent.
            $item = number_format($item, 0, '', '');
        }

        if (is_int($item)) {
            $item = (string) $item;
        }

        if (!is_string($item)) {
            return null;
        }

        $number = trim($item, self::NUMBER_WRAPPING_CHARACTERS);

        if ($number === '') {
            return null;
        }

        return $number;
    }
}
