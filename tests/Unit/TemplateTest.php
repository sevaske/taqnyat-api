<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Template\Template;
use Sevaske\Taqnyat\Template\TemplateRegistry;

final class TemplateTest extends TestCase
{
    public function testRendersPlaceholders(): void
    {
        $template = new Template('Your code is {code}. Valid for { minutes } min. Code again: {code}');

        self::assertSame(['code', 'minutes'], $template->getPlaceholders());
        self::assertTrue($template->hasPlaceholder('minutes'));
        self::assertFalse($template->hasPlaceholder('name'));
        self::assertSame(
            'Your code is 4821. Valid for 5 min. Code again: 4821',
            $template->render(['code' => 4821, 'minutes' => 5, 'unused' => 'x']),
        );
    }

    public function testRendersArabicAndStringableValues(): void
    {
        $name = new class {
            public function __toString(): string
            {
                return 'أحمد';
            }
        };

        $template = Template::create('مرحبا {user.name}، رصيدك {balance} ريال');

        self::assertSame('مرحبا أحمد، رصيدك 10.5 ريال', $template->render(['user.name' => $name, 'balance' => 10.5]));
    }

    public function testLeavesNonPlaceholderBracesUntouched(): void
    {
        $template = new Template('JSON {"a":1} and {1abc} stay, {name} changes');

        self::assertSame(['name'], $template->getPlaceholders());
        self::assertSame('JSON {"a":1} and {1abc} stay, Bob changes', $template->render(['name' => 'Bob']));
    }

    public function testTemplateWithoutPlaceholders(): void
    {
        self::assertSame('Static text', (new Template('Static text'))->render());
    }

    public function testThrowsOnMissingPlaceholder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing value for template placeholder(s): minutes.');

        (new Template('{code} {minutes}'))->render(['code' => 1]);
    }

    public function testThrowsOnNonStringableValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('placeholder "code"');

        (new Template('{code}'))->render(['code' => ['x']]);
    }

    public function testThrowsOnEmptyBody(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Template('   ');
    }

    public function testRegistry(): void
    {
        $registry = new TemplateRegistry([
            'otp' => 'Your code is {code}',
            'welcome' => new Template('Welcome, {name}!'),
        ]);
        $registry->add('bye', 'Bye!');

        self::assertCount(3, $registry);
        self::assertSame(['otp', 'welcome', 'bye'], $registry->names());
        self::assertTrue($registry->has('otp'));
        self::assertFalse($registry->has('missing'));
        self::assertSame('Your code is 77', $registry->render('otp', ['code' => 77]));
        self::assertSame('Welcome, Ann!', $registry->get('welcome')->render(['name' => 'Ann']));
        self::assertContainsOnlyInstancesOf(Template::class, iterator_to_array($registry));
    }

    public function testRegistryThrowsOnUnknownTemplate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template "missing" is not registered.');

        (new TemplateRegistry())->get('missing');
    }

    public function testFromAcceptsTemplateOrString(): void
    {
        $template = new Template('Hi {name}');

        self::assertSame($template, Template::from($template));
        self::assertSame('Hi {name}', Template::from('Hi {name}')->getBody());
    }

    public function testFromRejectsOtherTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('integer given');

        Template::from(42);
    }
}
