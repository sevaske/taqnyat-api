<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Message\PhoneNumber;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Template\Template;

final class SmsMessageTest extends TestCase
{
    public function testBuildsMinimalPayload(): void
    {
        $message = SmsMessage::create(' Taqnyat.sa ', ['+966500000000', 966511111111, '00966500000000'], 'Hello');

        self::assertSame([
            'recipients' => ['966500000000', '966511111111'],
            'body' => 'Hello',
            'sender' => 'Taqnyat.sa',
        ], $message->toArray());
        self::assertFalse($message->isScheduled());
        self::assertNull($message->getDeleteId());
    }

    public function testAcceptsSingleRecipientAndIterables(): void
    {
        self::assertCount(1, SmsMessage::create('S', '966500000000', 'x')->getRecipients());
        self::assertCount(1, SmsMessage::create('S', PhoneNumber::from('966500000000'), 'x')->getRecipients());
        self::assertCount(2, SmsMessage::create('S', new \ArrayIterator(['966500000000', '966511111111']), 'x')->getRecipients());
    }

    public function testScheduleIsConvertedToRiyadhTimeByDefault(): void
    {
        $message = SmsMessage::create('S', '966500000000', 'x')
            ->scheduleAt(new \DateTimeImmutable('2030-09-30 11:26:59', new \DateTimeZone('UTC')))
            ->withDeleteId(3242424);

        self::assertTrue($message->isScheduled());
        self::assertSame('2030-09-30T14:26', $message->toArray()['scheduledDatetime'] ?? null);
        self::assertSame(3242424, $message->toArray()['deleteId'] ?? null);
        self::assertSame('2030-09-30T11:26', $message->toArray(new \DateTimeZone('UTC'))['scheduledDatetime'] ?? null);
    }

    public function testScheduleAcceptsMutableDateTimeWithoutKeepingReference(): void
    {
        $date = new \DateTime('2030-01-01 10:00', new \DateTimeZone('Asia/Riyadh'));
        $message = SmsMessage::create('S', '966500000000', 'x')->scheduleAt($date);
        $date->modify('+1 day');

        self::assertSame('2030-01-01T10:00', $message->toArray()['scheduledDatetime'] ?? null);
    }

    public function testIsImmutable(): void
    {
        $original = SmsMessage::create('S', '966500000000', 'x');

        $modified = $original
            ->withSender('Other')
            ->withBody('y')
            ->withAddedRecipients(['966511111111', '966500000000'])
            ->scheduleAt(new \DateTimeImmutable('2030-01-01'))
            ->withDeleteId(5);

        self::assertNotSame($original, $modified);
        self::assertSame('S', $original->getSender());
        self::assertSame('x', $original->getBody());
        self::assertCount(1, $original->getRecipients());
        self::assertFalse($original->isScheduled());
        self::assertNull($original->getDeleteId());

        self::assertSame('Other', $modified->getSender());
        self::assertSame('y', $modified->getBody());
        self::assertCount(2, $modified->getRecipients());

        $reset = $modified->withoutSchedule()->withoutDeleteId()->withRecipients('966522222222');
        self::assertFalse($reset->isScheduled());
        self::assertNull($reset->getDeleteId());
        self::assertSame('966522222222', $reset->getRecipients()[0]->toString());
    }

    public function testFromTemplate(): void
    {
        $message = SmsMessage::fromTemplate('S', '966500000000', new Template('Code: {code}'), ['code' => '0042']);

        self::assertSame('Code: 0042', $message->getBody());
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'empty sender' => [static function () {
            return SmsMessage::create('  ', '966500000000', 'x');
        }, 'Sender name must not be empty.'];
        yield 'empty body' => [static function () {
            return SmsMessage::create('S', '966500000000', " \n ");
        }, 'Message body must not be empty.'];
        yield 'invalid utf-8 body' => [static function () {
            return SmsMessage::create('S', '966500000000', "\xB1\x31");
        }, 'valid UTF-8'];
        yield 'no recipients' => [static function () {
            return SmsMessage::create('S', [], 'x');
        }, 'At least one recipient is required.'];
        yield 'invalid recipient' => [static function () {
            return SmsMessage::create('S', ['966500000000', 'abc'], 'x');
        }, 'Invalid phone number "abc"'];
        yield 'non-positive delete id' => [static function () {
            return SmsMessage::create('S', '966500000000', 'x')->withDeleteId(0);
        }, 'Delete ID must be a positive integer.'];
    }

    /**
     * @dataProvider invalidMessages
     *
     * @param callable(): mixed $factory
     */
    public function testValidation(callable $factory, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $factory();
    }
}
