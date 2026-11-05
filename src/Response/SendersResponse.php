<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response;

use Sevaske\Taqnyat\Response\Concerns\ReadsData;
use Sevaske\Taqnyat\Support\Text;

/**
 * Successful result of GET /v1/messages/senders.
 *
 *     foreach ($taqnyat->messages()->senders() as $sender) {
 *         echo $sender->getName(), ' ', $sender->getStatus(), PHP_EOL;
 *     }
 *
 * @implements \IteratorAggregate<int, Sender>
 */
final class SendersResponse implements \IteratorAggregate, \Countable
{
    use ReadsData;

    private int $statusCode;

    /**
     * @var list<Sender>
     */
    private array $senders;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param list<Sender>            $senders
     * @param array<array-key, mixed> $data
     */
    public function __construct(int $statusCode, array $senders, array $data = [])
    {
        $this->statusCode = $statusCode;
        $this->senders = $senders;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON response body
     */
    public static function fromArray(array $data): self
    {
        $statusCode = self::readInt($data, 'statusCode', 201);
        $senders = self::parseSenders($data['senders'] ?? null);

        return new self($statusCode, $senders, $data);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return list<Sender>
     */
    public function getSenders(): array
    {
        return $this->senders;
    }

    /**
     * Only senders with the "active" status.
     *
     * @return list<Sender>
     */
    public function getActive(): array
    {
        $activeSenders = [];

        foreach ($this->senders as $sender) {
            if ($sender->isActive()) {
                $activeSenders[] = $sender;
            }
        }

        return $activeSenders;
    }

    /**
     * Sender names.
     *
     * @return list<string>
     */
    public function getNames(): array
    {
        $names = [];

        foreach ($this->senders as $sender) {
            $names[] = $sender->getName();
        }

        return $names;
    }

    /**
     * Finds a sender by name (case-insensitive).
     */
    public function find(string $name): ?Sender
    {
        $name = trim($name);

        foreach ($this->senders as $sender) {
            if (Text::equalsIgnoringCase($sender->getName(), $name)) {
                return $sender;
            }
        }

        return null;
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
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
     * @return \ArrayIterator<int, Sender>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->senders);
    }

    public function count(): int
    {
        return count($this->senders);
    }

    /**
     * Skips malformed entries instead of failing the whole response.
     *
     * @param mixed $senders
     *
     * @return list<Sender>
     */
    private static function parseSenders($senders): array
    {
        if (!is_array($senders)) {
            return [];
        }

        $parsed = [];

        foreach ($senders as $sender) {
            if (is_array($sender)) {
                $parsed[] = Sender::fromArray($sender);
            }
        }

        return $parsed;
    }
}
