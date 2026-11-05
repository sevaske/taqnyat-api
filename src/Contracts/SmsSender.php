<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Contracts;

use Sevaske\Taqnyat\Exception\TaqnyatException;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\SendSmsResponse;

/**
 * Abstraction over SMS sending. Type-hint against it in your application code
 * to make it easy to replace with a fake in tests.
 */
interface SmsSender
{
    /**
     * @throws TaqnyatException
     */
    public function send(SmsMessage $message): SendSmsResponse;
}
