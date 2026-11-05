<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Response;

use Sevaske\Taqnyat\Response\Concerns\ReadsData;
use Sevaske\Taqnyat\Support\Text;

/**
 * Successful result of GET /account/balance.
 */
final class BalanceResponse
{
    use ReadsData;

    /**
     * Format of "accountExpiryDate", e.g. 15-03-2021.
     */
    public const EXPIRY_DATE_FORMAT = 'd-m-Y';

    public const ACCOUNT_STATUS_ACTIVE = 'active';

    /**
     * Timezone of the expiry date: Taqnyat is a Saudi service.
     */
    private const TIMEZONE = 'Asia/Riyadh';

    private int $statusCode;

    private string $accountStatus;

    private string $accountExpiryDate;

    private string $balance;

    private string $currency;

    private string $points;

    /**
     * @var array<array-key, mixed>
     */
    private array $data;

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        int $statusCode,
        string $accountStatus,
        string $accountExpiryDate,
        string $balance,
        string $currency,
        string $points,
        array $data = []
    ) {
        $this->statusCode = $statusCode;
        $this->accountStatus = $accountStatus;
        $this->accountExpiryDate = $accountExpiryDate;
        $this->balance = $balance;
        $this->currency = $currency;
        $this->points = $points;
        $this->data = $data;
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON response body
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::readInt($data, 'statusCode', 200),
            self::readString($data, 'accountStatus'),
            self::readString($data, 'accountExpiryDate'),
            self::readString($data, 'balance', '0'),
            self::readString($data, 'currency'),
            self::readString($data, 'points', '0'),
            $data,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Account status as reported by Taqnyat, e.g. "active".
     */
    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }

    public function isActive(): bool
    {
        return Text::equalsIgnoringCase($this->accountStatus, self::ACCOUNT_STATUS_ACTIVE);
    }

    /**
     * Credit expiry date exactly as returned by the API, e.g. "15-03-2021".
     */
    public function getAccountExpiryDate(): string
    {
        return $this->accountExpiryDate;
    }

    /**
     * Credit expiry date (start of day, Asia/Riyadh), or null when it is missing or cannot be parsed.
     */
    public function getExpiryDate(): ?\DateTimeImmutable
    {
        if ($this->accountExpiryDate === '') {
            return null;
        }

        // "!" resets the time to midnight, so the result is the start of the day.
        $format = '!' . self::EXPIRY_DATE_FORMAT;
        $timezone = new \DateTimeZone(self::TIMEZONE);

        $date = \DateTimeImmutable::createFromFormat($format, $this->accountExpiryDate, $timezone);

        if ($date === false) {
            return null;
        }

        // createFromFormat() silently rolls invalid dates over ("31-02" becomes "03-03"): reject them.
        $isSameDateAsInput = $date->format(self::EXPIRY_DATE_FORMAT) === $this->accountExpiryDate;

        if (!$isSameDateAsInput) {
            return null;
        }

        return $date;
    }

    /**
     * Balance as a decimal string (e.g. "100.0000"), kept as string to avoid float rounding.
     */
    public function getBalance(): string
    {
        return $this->balance;
    }

    /**
     * Balance currency, e.g. "SAR".
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Account points as a numeric string, e.g. "667".
     */
    public function getPoints(): string
    {
        return $this->points;
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
