<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Api;

use Sevaske\Taqnyat\Exception\ApiException;
use Sevaske\Taqnyat\Exception\TransportException;
use Sevaske\Taqnyat\Http\HttpTransport;
use Sevaske\Taqnyat\Response\BalanceResponse;

/**
 * Account endpoints of the Taqnyat API.
 */
final class Account
{
    private const BALANCE_PATH = '/account/balance';

    private HttpTransport $transport;

    /**
     * @internal use {@see \Sevaske\Taqnyat\Client::account()}
     */
    public function __construct(HttpTransport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Returns the account balance, points and credit expiry date.
     *
     * @throws TransportException on network failure
     * @throws ApiException       when Taqnyat rejects the request
     */
    public function balance(): BalanceResponse
    {
        $data = $this->transport->request('GET', self::BALANCE_PATH);

        return BalanceResponse::fromArray($data);
    }
}
