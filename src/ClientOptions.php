<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;

/**
 * Immutable optional configuration of {@see Client}.
 *
 *     $options = ClientOptions::create()
 *         ->withHttpClient($guzzle)          // any PSR-18 client
 *         ->withTimezone('Asia/Riyadh');     // timezone of scheduledDatetime
 *
 * Anything not configured explicitly is discovered automatically (php-http/discovery).
 */
final class ClientOptions
{
    public const DEFAULT_BASE_URI = 'https://api.taqnyat.sa';
    public const DEFAULT_TIMEZONE = 'Asia/Riyadh';

    private ?ClientInterface $httpClient = null;

    private ?RequestFactoryInterface $requestFactory = null;

    private ?StreamFactoryInterface $streamFactory = null;

    private string $baseUri = self::DEFAULT_BASE_URI;

    private \DateTimeZone $timezone;

    private ?string $userAgent = null;

    public function __construct()
    {
        $this->timezone = new \DateTimeZone(self::DEFAULT_TIMEZONE);
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Uses the given PSR-18 HTTP client (configure timeouts, proxies, retries etc. on it).
     */
    public function withHttpClient(ClientInterface $httpClient): self
    {
        $clone = clone $this;
        $clone->httpClient = $httpClient;

        return $clone;
    }

    public function withRequestFactory(RequestFactoryInterface $requestFactory): self
    {
        $clone = clone $this;
        $clone->requestFactory = $requestFactory;

        return $clone;
    }

    public function withStreamFactory(StreamFactoryInterface $streamFactory): self
    {
        $clone = clone $this;
        $clone->streamFactory = $streamFactory;

        return $clone;
    }

    /**
     * Overrides the API base URI (useful for proxies and testing).
     */
    public function withBaseUri(string $baseUri): self
    {
        $baseUri = rtrim(trim($baseUri), '/');

        if (!self::isHttpUrl($baseUri)) {
            throw new InvalidArgumentException(sprintf('Invalid base URI "%s".', $baseUri));
        }

        $clone = clone $this;
        $clone->baseUri = $baseUri;

        return $clone;
    }

    /**
     * Timezone in which "scheduledDatetime" is sent to Taqnyat (defaults to Asia/Riyadh).
     *
     * @param \DateTimeZone|string $timezone
     */
    public function withTimezone($timezone): self
    {
        $clone = clone $this;
        $clone->timezone = self::toTimezone($timezone);

        return $clone;
    }

    /**
     * Overrides the User-Agent header sent with every request.
     */
    public function withUserAgent(string $userAgent): self
    {
        $clone = clone $this;
        $clone->userAgent = $userAgent;

        return $clone;
    }

    public function getHttpClient(): ?ClientInterface
    {
        return $this->httpClient;
    }

    public function getRequestFactory(): ?RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function getStreamFactory(): ?StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getTimezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    private static function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @param mixed $timezone
     */
    private static function toTimezone($timezone): \DateTimeZone
    {
        if ($timezone instanceof \DateTimeZone) {
            return $timezone;
        }

        if (!is_string($timezone)) {
            throw new InvalidArgumentException('Timezone must be a string or an instance of DateTimeZone.');
        }

        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Invalid timezone "%s".', $timezone), 0, $e);
        }
    }
}
