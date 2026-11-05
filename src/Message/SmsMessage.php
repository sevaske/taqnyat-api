<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Message;

use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Template\Template;

/**
 * An immutable SMS message to be sent through Taqnyat.
 *
 *     $message = SmsMessage::create('MySender', ['966500000000'], 'Hello!')
 *         ->scheduleAt(new DateTimeImmutable('+1 hour'))
 *         ->withDeleteId(1001);
 *
 * Every with*() method returns a new instance; the original is never modified.
 */
final class SmsMessage
{
    /**
     * Date format of the "scheduledDatetime" field, e.g. 2020-09-30T14:26.
     */
    public const SCHEDULE_FORMAT = 'Y-m-d\TH:i';

    /**
     * Timezone of "scheduledDatetime" when no other is given: Taqnyat is a Saudi service.
     */
    public const DEFAULT_TIMEZONE = 'Asia/Riyadh';

    private string $sender;

    /**
     * @var list<PhoneNumber>
     */
    private array $recipients;

    private string $body;

    private ?\DateTimeImmutable $scheduledAt = null;

    private ?int $deleteId = null;

    /**
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $sender, $recipients, string $body)
    {
        $this->sender = self::validateSender($sender);
        $this->recipients = self::parseRecipients($recipients);
        $this->body = self::validateBody($body);
    }

    /**
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $sender, $recipients, string $body): self
    {
        return new self($sender, $recipients, $body);
    }

    /**
     * Builds a message whose body is rendered from a template.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     * @param array<string, mixed>                                    $parameters
     *
     * @throws InvalidArgumentException
     */
    public static function fromTemplate(string $sender, $recipients, Template $template, array $parameters = []): self
    {
        $body = $template->render($parameters);

        return new self($sender, $recipients, $body);
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    /**
     * @return list<PhoneNumber>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * Recipients as plain strings, e.g. ["966500000000", "966511111111"].
     *
     * @return list<string>
     */
    public function getRecipientNumbers(): array
    {
        $numbers = [];

        foreach ($this->recipients as $recipient) {
            $numbers[] = $recipient->toString();
        }

        return $numbers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }

    public function getDeleteId(): ?int
    {
        return $this->deleteId;
    }

    public function withSender(string $sender): self
    {
        $clone = clone $this;
        $clone->sender = self::validateSender($sender);

        return $clone;
    }

    /**
     * Replaces all recipients.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     */
    public function withRecipients($recipients): self
    {
        $clone = clone $this;
        $clone->recipients = self::parseRecipients($recipients);

        return $clone;
    }

    /**
     * Adds recipients to the existing ones (duplicates are ignored).
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     */
    public function withAddedRecipients($recipients): self
    {
        $newRecipients = self::parseRecipients($recipients);
        $allRecipients = array_merge($this->recipients, $newRecipients);

        $clone = clone $this;
        $clone->recipients = self::removeDuplicates($allRecipients);

        return $clone;
    }

    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = self::validateBody($body);

        return $clone;
    }

    /**
     * Schedules the message to be sent later.
     *
     * Taqnyat accepts minute precision; seconds are discarded. The time is converted
     * to the client's configured timezone (Asia/Riyadh by default) when the request is built.
     */
    public function scheduleAt(\DateTimeInterface $dateTime): self
    {
        $clone = clone $this;
        $clone->scheduledAt = self::toImmutable($dateTime);

        return $clone;
    }

    /**
     * Removes scheduling: the message will be sent immediately.
     */
    public function withoutSchedule(): self
    {
        $clone = clone $this;
        $clone->scheduledAt = null;

        return $clone;
    }

    /**
     * Sets your own identifier which can later be used to delete a scheduled message.
     */
    public function withDeleteId(int $deleteId): self
    {
        if ($deleteId <= 0) {
            throw new InvalidArgumentException('Delete ID must be a positive integer.');
        }

        $clone = clone $this;
        $clone->deleteId = $deleteId;

        return $clone;
    }

    public function withoutDeleteId(): self
    {
        $clone = clone $this;
        $clone->deleteId = null;

        return $clone;
    }

    /**
     * Returns the request payload for POST /v1/messages.
     *
     * @param \DateTimeZone|null $timezone timezone the scheduled time is expressed in (defaults to Asia/Riyadh)
     *
     * @return array{recipients: list<string>, body: string, sender: string, scheduledDatetime?: string, deleteId?: int}
     */
    public function toArray(?\DateTimeZone $timezone = null): array
    {
        $payload = [
            'recipients' => $this->getRecipientNumbers(),
            'body' => $this->body,
            'sender' => $this->sender,
        ];

        if ($this->scheduledAt !== null) {
            $payload['scheduledDatetime'] = self::formatScheduledAt($this->scheduledAt, $timezone);
        }

        if ($this->deleteId !== null) {
            $payload['deleteId'] = $this->deleteId;
        }

        return $payload;
    }

    private static function formatScheduledAt(\DateTimeImmutable $scheduledAt, ?\DateTimeZone $timezone): string
    {
        $timezone = $timezone ?? new \DateTimeZone(self::DEFAULT_TIMEZONE);

        return $scheduledAt
            ->setTimezone($timezone)
            ->format(self::SCHEDULE_FORMAT);
    }

    private static function toImmutable(\DateTimeInterface $dateTime): \DateTimeImmutable
    {
        if ($dateTime instanceof \DateTime) {
            // Copy, so later changes to the caller's mutable object do not affect this message.
            return \DateTimeImmutable::createFromMutable($dateTime);
        }

        // The only other DateTimeInterface implementation is DateTimeImmutable.
        return $dateTime;
    }

    private static function validateSender(string $sender): string
    {
        $sender = trim($sender);

        if ($sender === '') {
            throw new InvalidArgumentException('Sender name must not be empty.');
        }

        return $sender;
    }

    private static function validateBody(string $body): string
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('Message body must not be empty.');
        }

        if (!self::isValidUtf8($body)) {
            throw new InvalidArgumentException('Message body must be a valid UTF-8 string.');
        }

        return $body;
    }

    private static function isValidUtf8(string $text): bool
    {
        // An empty pattern with the "u" modifier matches only valid UTF-8 (no mbstring needed).
        return preg_match('//u', $text) === 1;
    }

    /**
     * @param mixed $recipients
     *
     * @return list<PhoneNumber>
     */
    private static function parseRecipients($recipients): array
    {
        $phoneNumbers = [];

        foreach (self::wrapSingleRecipient($recipients) as $recipient) {
            $phoneNumbers[] = PhoneNumber::from($recipient);
        }

        $phoneNumbers = self::removeDuplicates($phoneNumbers);

        if ($phoneNumbers === []) {
            throw new InvalidArgumentException('At least one recipient is required.');
        }

        return $phoneNumbers;
    }

    /**
     * Turns a single recipient into a one-element list; lists are returned as is.
     *
     * @param mixed $recipients
     *
     * @return iterable<mixed>
     */
    private static function wrapSingleRecipient($recipients): iterable
    {
        $isSingleRecipient = is_string($recipients)
            || is_int($recipients)
            || $recipients instanceof PhoneNumber;

        if ($isSingleRecipient) {
            return [$recipients];
        }

        if (is_iterable($recipients)) {
            return $recipients;
        }

        throw new InvalidArgumentException('Recipients must be a phone number or a list of phone numbers.');
    }

    /**
     * @param list<PhoneNumber> $phoneNumbers
     *
     * @return list<PhoneNumber>
     */
    private static function removeDuplicates(array $phoneNumbers): array
    {
        $uniqueByNumber = [];

        foreach ($phoneNumbers as $phoneNumber) {
            $uniqueByNumber[$phoneNumber->toString()] = $phoneNumber;
        }

        return array_values($uniqueByNumber);
    }
}
