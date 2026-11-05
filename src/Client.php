<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat;

use Http\Discovery\Exception as DiscoveryException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sevaske\Taqnyat\Api\Account;
use Sevaske\Taqnyat\Api\Messages;
use Sevaske\Taqnyat\Api\System;
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Exception\ConfigurationException;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Http\HttpTransport;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\SendSmsResponse;

/**
 * Entry point of the Taqnyat API client.
 *
 *     $taqnyat = new Client('your-bearer-token');
 *
 *     $response = $taqnyat->messages()->sendText('MySender', '966500000000', 'Hello!');
 *     $balance  = $taqnyat->account()->balance();
 *     $status   = $taqnyat->system()->status();
 */
final class Client implements SmsSender
{
    public const VERSION = '1.0.0';

    private HttpTransport $transport;

    private ClientOptions $options;

    private ?Messages $messages = null;

    private ?Account $account = null;

    private ?System $system = null;

    /**
     * @param string             $token   bearer token from the Taqnyat dashboard
     * @param ClientOptions|null $options optional HTTP client, base URI, timezone, ...
     *
     * @throws InvalidArgumentException when the token is empty
     * @throws ConfigurationException   when no PSR-18 client / PSR-17 factory is available
     */
    public function __construct(string $token, ?ClientOptions $options = null)
    {
        $token = self::validateToken($token);

        $this->options = $options ?? new ClientOptions();

        $this->transport = new HttpTransport(
            $this->resolveHttpClient(),
            $this->resolveRequestFactory(),
            $this->resolveStreamFactory(),
            $this->options->getBaseUri(),
            $token,
            $this->resolveUserAgent(),
        );
    }

    public static function create(string $token, ?ClientOptions $options = null): self
    {
        return new self($token, $options);
    }

    /**
     * Messages endpoints: send, delete scheduled messages, sender names.
     */
    public function messages(): Messages
    {
        if ($this->messages === null) {
            $this->messages = new Messages($this->transport, $this->options->getTimezone());
        }

        return $this->messages;
    }

    /**
     * Account endpoints: balance.
     */
    public function account(): Account
    {
        if ($this->account === null) {
            $this->account = new Account($this->transport);
        }

        return $this->account;
    }

    /**
     * System endpoints: service status.
     */
    public function system(): System
    {
        if ($this->system === null) {
            $this->system = new System($this->transport);
        }

        return $this->system;
    }

    /**
     * Shortcut for messages()->send().
     */
    public function send(SmsMessage $message): SendSmsResponse
    {
        return $this->messages()->send($message);
    }

    public function getOptions(): ClientOptions
    {
        return $this->options;
    }

    private static function validateToken(string $token): string
    {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException('Taqnyat bearer token must not be empty.');
        }

        return $token;
    }

    private function resolveHttpClient(): ClientInterface
    {
        $httpClient = $this->options->getHttpClient();

        if ($httpClient !== null) {
            return $httpClient;
        }

        try {
            return Psr18ClientDiscovery::find();
        } catch (DiscoveryException $e) {
            throw self::discoveryFailed('PSR-18 HTTP client', $e);
        }
    }

    private function resolveRequestFactory(): RequestFactoryInterface
    {
        $requestFactory = $this->options->getRequestFactory();

        if ($requestFactory !== null) {
            return $requestFactory;
        }

        try {
            return Psr17FactoryDiscovery::findRequestFactory();
        } catch (DiscoveryException $e) {
            throw self::discoveryFailed('PSR-17 request factory', $e);
        }
    }

    private function resolveStreamFactory(): StreamFactoryInterface
    {
        $streamFactory = $this->options->getStreamFactory();

        if ($streamFactory !== null) {
            return $streamFactory;
        }

        try {
            return Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryException $e) {
            throw self::discoveryFailed('PSR-17 stream factory', $e);
        }
    }

    private function resolveUserAgent(): string
    {
        $userAgent = $this->options->getUserAgent();

        if ($userAgent !== null) {
            return $userAgent;
        }

        return sprintf('sevaske-taqnyat-php/%s (PHP %s)', self::VERSION, PHP_VERSION);
    }

    private static function discoveryFailed(string $missingComponent, DiscoveryException $previous): ConfigurationException
    {
        $message = sprintf(
            'No %s found. Install one (e.g. "composer require guzzlehttp/guzzle") or pass it explicitly via ClientOptions.',
            $missingComponent,
        );

        return new ConfigurationException($message, 0, $previous);
    }
}
