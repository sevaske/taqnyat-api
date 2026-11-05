<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Api;

use Sevaske\Taqnyat\Exception\ApiException;
use Sevaske\Taqnyat\Exception\ServerException;
use Sevaske\Taqnyat\Exception\TransportException;
use Sevaske\Taqnyat\Http\HttpTransport;
use Sevaske\Taqnyat\Response\SystemStatusResponse;

/**
 * System endpoints of the Taqnyat API.
 */
final class System
{
    private const STATUS_PATH = '/system/status';

    private const MAINTENANCE_STATUS_CODE = 503;

    private HttpTransport $transport;

    /**
     * @internal use {@see \Sevaske\Taqnyat\Client::system()}
     */
    public function __construct(HttpTransport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Returns the Taqnyat SMS service status. This endpoint does not require authentication,
     * so the bearer token is not sent.
     *
     * Maintenance (HTTP 503 with a status body) is returned as a response with
     * {@see SystemStatusResponse::isOperational()} === false rather than thrown.
     *
     * @throws TransportException on network failure
     * @throws ApiException       on any other error (e.g. 403)
     */
    public function status(): SystemStatusResponse
    {
        try {
            $data = $this->transport->requestWithoutToken('GET', self::STATUS_PATH);
        } catch (ServerException $e) {
            if (!self::isMaintenanceResponse($e)) {
                throw $e;
            }

            return SystemStatusResponse::fromArray($e->getResponseData(), self::MAINTENANCE_STATUS_CODE);
        }

        return SystemStatusResponse::fromArray($data, 200);
    }

    /**
     * Maintenance is reported as HTTP 503 with a body like
     * {"status": {"service": "Rest API (HTTPS)", "description": "Down for Maintenance"}}.
     */
    private static function isMaintenanceResponse(ServerException $exception): bool
    {
        if ($exception->getStatusCode() !== self::MAINTENANCE_STATUS_CODE) {
            return false;
        }

        $status = $exception->getResponseData()['status'] ?? null;

        return is_array($status);
    }
}
