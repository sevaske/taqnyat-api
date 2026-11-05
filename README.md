# Taqnyat SMS PHP Client

[![CI](https://github.com/sevaske/taqnyat-api/actions/workflows/ci.yml/badge.svg)](https://github.com/sevaske/taqnyat-api/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/sevaske/taqnyat-api.svg)](https://packagist.org/packages/sevaske/taqnyat-api)
[![PHP Version](https://img.shields.io/packagist/php-v/sevaske/taqnyat-api.svg)](https://packagist.org/packages/sevaske/taqnyat-api)
[![License](https://img.shields.io/packagist/l/sevaske/taqnyat-api.svg)](LICENSE)

A framework-agnostic PHP client for the [Taqnyat](https://www.taqnyat.sa) SMS API. Covers every endpoint of the public SMS API.

- Send plain text, templated and scheduled SMS, delete scheduled messages
- Account balance, sender names and service status
- Works with **any PSR-18 HTTP client** (Guzzle, Symfony HttpClient, ...), auto-discovered
- Immutable, validated value objects: invalid input fails fast, before an HTTP request is made
- Typed responses and a clear exception hierarchy
- PHP 7.4 – 8.5, no framework dependencies, PHPStan level max

> This is an unofficial, community-maintained library and is not affiliated with Taqnyat.

## Installation

```bash
composer require sevaske/taqnyat-api
```

The library talks HTTP through [PSR-18](https://www.php-fig.org/psr/psr-18/). If your project has no PSR-18 client yet, install one, for example:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Sevaske\Taqnyat\Client;

$taqnyat = new Client('your-bearer-token');

$response = $taqnyat->messages()->sendText(
    'MySender',                          // sender name registered in your Taqnyat account
    ['966500000000', '+966 51 111 1111'], // recipients
    'Hello from Taqnyat!'
);

echo $response->getMessageId(); // "5829452722"
echo $response->getCost();      // "0.1500"
echo $response->getCurrency();  // "SAR"
```

Get your bearer token in the Taqnyat dashboard ([how to set up a bearer token](https://www.taqnyat.sa/documentation/sms/#setup-bearer)).

## Sending messages

### The `SmsMessage` object

`SmsMessage` is an immutable value object. Every `with*()` method returns a new instance.

```php
use Sevaske\Taqnyat\Message\SmsMessage;

$message = SmsMessage::create('MySender', ['966500000000', '966511111111'], 'Your order has shipped');

$response = $taqnyat->messages()->send($message);
// or the shortcut:
$response = $taqnyat->send($message);
```

Available modifiers: `withSender()`, `withBody()`, `withRecipients()`, `withAddedRecipients()`,
`scheduleAt()`, `withoutSchedule()`, `withDeleteId()`, `withoutDeleteId()`.

### Recipients

Recipients are normalized to the international format Taqnyat expects (country code, digits only)
and de-duplicated. All of these become `966500000000`:

```php
'966500000000'
966500000000
'+966500000000'
'00966500000000'
'+966 (50) 000-0000'
```

Invalid numbers (letters, local format like `0500000000`, wrong length) throw an
`InvalidArgumentException`. You can check a number upfront with `PhoneNumber::isValid($number)`.

### Scheduled messages

```php
$message = SmsMessage::create('MySender', '966500000000', 'Reminder: your appointment is tomorrow')
    ->scheduleAt(new DateTimeImmutable('2030-09-30 14:26', new DateTimeZone('Asia/Riyadh')))
    ->withDeleteId(3242424); // optional: your own ID to delete the scheduled message later

$taqnyat->send($message);
```

Taqnyat accepts minute precision (`2030-09-30T14:26`). The date is **converted to `Asia/Riyadh`** before
sending, so you can pass a date in any timezone. Change the target timezone with
`ClientOptions::withTimezone()` if needed.

## Templates

A template is a message body with `{placeholders}`:

```php
use Sevaske\Taqnyat\Template\Template;

$otp = new Template('Your verification code is {code}. It expires in {minutes} minutes.');

$taqnyat->messages()->sendTemplate('MySender', '966500000000', $otp, [
    'code'    => '4821',
    'minutes' => 5,
]);

// A raw string works too:
$taqnyat->messages()->sendTemplate('MySender', '966500000000', 'Hi {name}!', ['name' => 'Sara']);

// Or build the message yourself:
$message = SmsMessage::fromTemplate('MySender', '966500000000', $otp, ['code' => '4821', 'minutes' => 5]);
```

Rendering is **strict**: if a placeholder has no value, an `InvalidArgumentException` is thrown and nothing
is sent, so customers never receive `Your code is {code}`. Extra parameters are ignored.

Placeholder syntax: `{name}` or `{ name }`; names may contain letters, digits, `_` and `.`
(e.g. `{user.first_name}`) and must not start with a digit. Other braces (`{"a":1}`, `{1}`) are left as is.
Values may be strings, numbers or objects with `__toString()`.

### Template registry

Keep all your SMS texts in one place, e.g. loaded from config:

```php
use Sevaske\Taqnyat\Template\TemplateRegistry;

$templates = new TemplateRegistry([
    'otp'     => 'Your verification code is {code}',
    'welcome' => 'Welcome to {app}, {name}!',
]);

$taqnyat->messages()->sendTemplate('MySender', $phone, $templates->get('otp'), ['code' => $code]);
```

## Deleting scheduled messages

Set your own `deleteId` when scheduling, then use it to cancel the message before it is sent:

```php
$taqnyat->send(
    SmsMessage::create('MySender', '966500000000', 'Sale starts tomorrow!')
        ->scheduleAt(new DateTimeImmutable('tomorrow 09:00', new DateTimeZone('Asia/Riyadh')))
        ->withDeleteId(3242424)
);

$result = $taqnyat->messages()->delete(3242424);
echo $result->getMessage(); // "Deleted successfully"
```

An unknown `deleteId` throws `ValidationException` (HTTP 422).

## Sender names

```php
$senders = $taqnyat->messages()->senders();

foreach ($senders as $sender) {
    echo $sender->getName(), ' | ', $sender->getStatus(), ' | ', $sender->getDestination(), PHP_EOL;
}

$senders->getNames();          // ['sender name 1', 'sender name 2']
$senders->getActive();         // only active senders
$senders->has('MySender');     // case-insensitive lookup

if (null !== $sender = $senders->find('MySender')) {
    $sender->isActive();        // status === "active"
    $sender->isLocal();         // destination === "KSA"
    $sender->isInternational(); // destination === "international"
}
```

## Account balance

```php
$balance = $taqnyat->account()->balance();

$balance->getBalance();           // "100.0000" (decimal string, no float rounding)
$balance->getCurrency();          // "SAR"
$balance->getPoints();            // "667"
$balance->getAccountStatus();     // "active"
$balance->isActive();             // true
$balance->getAccountExpiryDate(); // "15-03-2031" (raw)
$balance->getExpiryDate();        // DateTimeImmutable 2031-03-15 00:00 Asia/Riyadh, or null
```

## System status

This endpoint needs no authentication, so the token is not sent.

```php
$status = $taqnyat->system()->status();

if ($status->isUnderMaintenance()) {
    // e.g. queue messages and retry later
    echo $status->getService(), ': ', $status->getDescription(); // "Rest API (HTTPS): Down for Maintenance"
}
```

Maintenance (HTTP 503) is returned as a status with `isOperational() === false` instead of being thrown.
Other errors (e.g. 403) are still thrown.

## Responses

### Send responses

`send()`, `sendText()` and `sendTemplate()` return `Sevaske\Taqnyat\Response\SendSmsResponse`:

| Method                | Description                                          |
|-----------------------|------------------------------------------------------|
| `getMessageId()`      | Taqnyat message ID (string)                          |
| `getCost()`           | Cost as a decimal string, e.g. `"0.1500"`            |
| `getCurrency()`       | Currency, e.g. `"SAR"`                               |
| `getTotalCount()`     | Number of accepted recipients                        |
| `getMessageLength()`  | Message length reported by Taqnyat                   |
| `getAccepted()`       | Accepted numbers, `list<string>`                     |
| `getRejected()`       | Rejected numbers, `list<string>`                     |
| `hasRejected()`       | `true` if at least one number was rejected           |
| `toArray()`           | Raw decoded response                                 |

A successful response does not guarantee every recipient was accepted, so check `hasRejected()`.

Every response object has `toArray()`, which returns the raw decoded body.

### API reference

| Endpoint                         | Method                             | Returns                 |
|----------------------------------|------------------------------------|-------------------------|
| `POST /v1/messages`              | `messages()->send()` / `sendText()` / `sendTemplate()` | `SendSmsResponse` |
| `DELETE /v1/messages/delete`     | `messages()->delete(int $deleteId)`| `DeleteMessageResponse` |
| `GET /v1/messages/senders`       | `messages()->senders()`            | `SendersResponse`       |
| `GET /account/balance`           | `account()->balance()`             | `BalanceResponse`       |
| `GET /system/status`             | `system()->status()`               | `SystemStatusResponse`  |

## Error handling

Every exception thrown by the library implements `Sevaske\Taqnyat\Exception\TaqnyatException`.

```
TaqnyatException (interface)
├── InvalidArgumentException      invalid input, thrown before any request
├── ConfigurationException        no PSR-18 client / PSR-17 factory found
├── TransportException            network error; it is unknown whether the SMS was sent
└── ApiException                  Taqnyat returned an error
    ├── BadRequestException         400, e.g. "Sender Name not active"
    ├── AuthenticationException     401, wrong bearer token
    ├── ForbiddenException          403
    ├── MethodNotAllowedException   405
    ├── ValidationException         422, e.g. invalid deleteId
    ├── RateLimitException          429
    ├── ServerException             5xx, including maintenance
    └── UnexpectedResponseException success status, but the body could not be parsed
```

```php
use Sevaske\Taqnyat\Exception\ApiException;
use Sevaske\Taqnyat\Exception\AuthenticationException;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\TransportException;

try {
    $response = $taqnyat->send($message);
} catch (InvalidArgumentException $e) {
    // bad phone number, empty body, missing template value...
} catch (AuthenticationException $e) {
    // check your token
} catch (ApiException $e) {
    $e->getStatusCode();   // e.g. 400
    $e->getMessage();      // message from Taqnyat, e.g. "Sender Name not active ."
    $e->getResponseData(); // decoded error body
    $e->getResponse();     // PSR-7 response
} catch (TransportException $e) {
    // timeout / DNS / connection error: retry carefully, the message may have been sent
}
```

## Configuration

All options are optional. Anything you don't set is discovered automatically.

```php
use Sevaske\Taqnyat\Client;
use Sevaske\Taqnyat\ClientOptions;

$options = ClientOptions::create()
    ->withHttpClient($psr18Client)           // e.g. Guzzle with your timeouts / proxy / retry middleware
    ->withRequestFactory($psr17Factory)
    ->withStreamFactory($psr17Factory)
    ->withTimezone('Asia/Riyadh')            // timezone of scheduledDatetime (default)
    ->withBaseUri('https://api.taqnyat.sa')  // default
    ->withUserAgent('MyApp/1.0');

$taqnyat = new Client('your-bearer-token', $options);
```

Example with Guzzle timeouts:

```php
$taqnyat = new Client($token, ClientOptions::create()->withHttpClient(
    new GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5])
));
```

## Testing your application

Type-hint `Sevaske\Taqnyat\Contracts\SmsSender` in your services. Both `Client` and `Client::messages()`
implement it, so you can replace them with a fake in tests:

```php
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\SendSmsResponse;

final class FakeSmsSender implements SmsSender
{
    /** @var list<SmsMessage> */
    public array $sent = [];

    public function send(SmsMessage $message): SendSmsResponse
    {
        $this->sent[] = $message;

        return SendSmsResponse::fromArray(['messageId' => 'fake', 'accepted' => '[]', 'rejected' => '[]']);
    }
}
```

You can also inject a mock PSR-18 client, such as [`php-http/mock-client`](https://github.com/php-http/mock-client),
via `ClientOptions::withHttpClient()`.

## Development

```bash
composer install
composer test           # PHPUnit
composer analyse        # PHPStan (level max)
```

Code style is enforced with [Laravel Pint](https://laravel.com/docs/pint) (PER Coding Style, see `pint.json`).
Pint requires PHP 8.3+, so it lives in its own `tools/pint` directory and does not affect the library's
PHP 7.4+ dependencies:

```bash
composer pint:install   # once
composer format         # fix code style
composer format:check   # check only (runs in CI)
```

## Contributing

Pull requests are welcome. Please add tests for your changes and make sure `composer check` and `composer format:check` pass.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
