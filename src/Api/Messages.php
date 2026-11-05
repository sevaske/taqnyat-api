<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Api;

use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Exception\ApiException;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\TransportException;
use Sevaske\Taqnyat\Http\HttpTransport;
use Sevaske\Taqnyat\Message\PhoneNumber;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\DeleteMessageResponse;
use Sevaske\Taqnyat\Response\SendersResponse;
use Sevaske\Taqnyat\Response\SendSmsResponse;
use Sevaske\Taqnyat\Template\Template;

/**
 * Messages endpoints of the Taqnyat API.
 */
final class Messages implements SmsSender
{
    private const SEND_PATH = '/v1/messages';
    private const DELETE_PATH = '/v1/messages/delete';
    private const SENDERS_PATH = '/v1/messages/senders';

    private HttpTransport $transport;

    private \DateTimeZone $timezone;

    /**
     * @internal use {@see \Sevaske\Taqnyat\Client::messages()}
     */
    public function __construct(HttpTransport $transport, \DateTimeZone $timezone)
    {
        $this->transport = $transport;
        $this->timezone = $timezone;
    }

    /**
     * Sends (or schedules) an SMS message.
     *
     * @throws InvalidArgumentException when the message cannot be encoded
     * @throws TransportException       on network failure
     * @throws ApiException             when Taqnyat rejects the request
     */
    public function send(SmsMessage $message): SendSmsResponse
    {
        $payload = $message->toArray($this->timezone);
        $data = $this->transport->request('POST', self::SEND_PATH, $payload);

        return SendSmsResponse::fromArray($data);
    }

    /**
     * Shortcut for sending a plain text message.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     *
     * @throws InvalidArgumentException
     * @throws TransportException
     * @throws ApiException
     */
    public function sendText(string $sender, $recipients, string $body): SendSmsResponse
    {
        $message = SmsMessage::create($sender, $recipients, $body);

        return $this->send($message);
    }

    /**
     * Renders the template with the given parameters and sends the result.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     * @param Template|string                                         $template   template instance or raw template body
     * @param array<string, mixed>                                    $parameters
     *
     * @throws InvalidArgumentException when a placeholder value is missing
     * @throws TransportException
     * @throws ApiException
     */
    public function sendTemplate(string $sender, $recipients, $template, array $parameters = []): SendSmsResponse
    {
        $template = Template::from($template);
        $message = SmsMessage::fromTemplate($sender, $recipients, $template, $parameters);

        return $this->send($message);
    }

    /**
     * Deletes a scheduled message by the delete ID that was set with {@see SmsMessage::withDeleteId()}.
     *
     * @throws InvalidArgumentException when the delete ID is not positive
     * @throws TransportException       on network failure
     * @throws ApiException             e.g. {@see \Sevaske\Taqnyat\Exception\ValidationException} for an unknown delete ID
     */
    public function delete(int $deleteId): DeleteMessageResponse
    {
        if ($deleteId <= 0) {
            throw new InvalidArgumentException('Delete ID must be a positive integer.');
        }

        $payload = ['deleteId' => $deleteId];
        $data = $this->transport->request('DELETE', self::DELETE_PATH, $payload);

        return DeleteMessageResponse::fromArray($data);
    }

    /**
     * Returns the sender names registered in your account.
     *
     * @throws TransportException on network failure
     * @throws ApiException       when Taqnyat rejects the request
     */
    public function senders(): SendersResponse
    {
        $data = $this->transport->request('GET', self::SENDERS_PATH);

        return SendersResponse::fromArray($data);
    }
}
