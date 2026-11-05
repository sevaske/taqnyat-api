<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Template;

use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Support\Type;

/**
 * A reusable SMS body with named placeholders.
 *
 *     $template = new Template('Your verification code is {code}. Valid for {minutes} minutes.');
 *     $template->render(['code' => 4821, 'minutes' => 5]);
 *
 * Placeholders are written as {name} (whitespace inside braces is allowed: { name }).
 * Names may contain letters, digits, "_" and "." and must not start with a digit.
 * Rendering is strict: every placeholder must receive a value, otherwise an exception is thrown,
 * so a half-filled message is never sent to a customer.
 */
final class Template
{
    /**
     * Matches "{name}" or "{ name }" and captures "name".
     */
    private const PLACEHOLDER_PATTERN = '/\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}/u';

    private string $body;

    /**
     * @var list<string>
     */
    private array $placeholders;

    /**
     * @throws InvalidArgumentException when the body is empty
     */
    public function __construct(string $body)
    {
        if (trim($body) === '') {
            throw new InvalidArgumentException('Template body must not be empty.');
        }

        $this->body = $body;
        $this->placeholders = self::findPlaceholders($body);
    }

    public static function create(string $body): self
    {
        return new self($body);
    }

    /**
     * Accepts a Template instance as is, or creates one from a template body string.
     *
     * @param Template|string|mixed $template
     *
     * @throws InvalidArgumentException
     */
    public static function from($template): self
    {
        if ($template instanceof self) {
            return $template;
        }

        if (is_string($template)) {
            return new self($template);
        }

        $message = sprintf(
            'Template must be a string or an instance of %s, %s given.',
            self::class,
            Type::describe($template),
        );

        throw new InvalidArgumentException($message);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Unique placeholder names in order of first appearance.
     *
     * @return list<string>
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function hasPlaceholder(string $name): bool
    {
        return in_array($name, $this->placeholders, true);
    }

    /**
     * Replaces placeholders with the given values.
     *
     * Values may be strings, integers, floats or objects implementing __toString().
     * Extra parameters that do not match any placeholder are ignored.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws InvalidArgumentException when a placeholder value is missing or not stringable
     */
    public function render(array $parameters = []): string
    {
        $this->assertAllPlaceholdersHaveValues($parameters);

        $values = $this->convertValuesToStrings($parameters);

        return $this->replacePlaceholders($values);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function assertAllPlaceholdersHaveValues(array $parameters): void
    {
        $missing = [];

        foreach ($this->placeholders as $name) {
            if (!array_key_exists($name, $parameters)) {
                $missing[] = $name;
            }
        }

        if ($missing === []) {
            return;
        }

        $message = sprintf('Missing value for template placeholder(s): %s.', implode(', ', $missing));

        throw new InvalidArgumentException($message);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, string> placeholder name => value
     */
    private function convertValuesToStrings(array $parameters): array
    {
        $values = [];

        foreach ($this->placeholders as $name) {
            $values[$name] = self::valueToString($name, $parameters[$name]);
        }

        return $values;
    }

    /**
     * @param array<string, string> $values placeholder name => value
     */
    private function replacePlaceholders(array $values): string
    {
        $rendered = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static function (array $match) use ($values): string {
                $placeholderName = $match[1];

                return $values[$placeholderName];
            },
            $this->body,
        );

        if ($rendered === null) {
            throw new InvalidArgumentException('Failed to render template: body is not valid UTF-8.');
        }

        return $rendered;
    }

    /**
     * @return list<string>
     */
    private static function findPlaceholders(string $body): array
    {
        $result = preg_match_all(self::PLACEHOLDER_PATTERN, $body, $matches);

        if ($result === false) {
            throw new InvalidArgumentException('Template body is not valid UTF-8.');
        }

        $placeholderNames = $matches[1];

        return array_values(array_unique($placeholderNames));
    }

    /**
     * @param mixed $value
     */
    private static function valueToString(string $placeholderName, $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        $message = sprintf(
            'Value for template placeholder "%s" must be a string, number or stringable object, %s given.',
            $placeholderName,
            Type::describe($value),
        );

        throw new InvalidArgumentException($message);
    }
}
