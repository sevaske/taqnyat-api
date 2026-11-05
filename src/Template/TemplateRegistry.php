<?php

declare(strict_types=1);

namespace Sevaske\Taqnyat\Template;

use Sevaske\Taqnyat\Exception\InvalidArgumentException;

/**
 * A named collection of templates, convenient for keeping all application SMS texts in one place
 * (e.g. loaded from a config file).
 *
 *     $templates = new TemplateRegistry([
 *         'otp'     => 'Your code is {code}',
 *         'welcome' => 'Welcome, {name}!',
 *     ]);
 *
 *     $templates->get('otp')->render(['code' => 1234]);
 *
 * @implements \IteratorAggregate<string, Template>
 */
final class TemplateRegistry implements \IteratorAggregate, \Countable
{
    /**
     * @var array<string, Template>
     */
    private array $templates = [];

    /**
     * @param iterable<string, Template|string> $templates
     */
    public function __construct(iterable $templates = [])
    {
        foreach ($templates as $name => $template) {
            $this->add((string) $name, $template);
        }
    }

    /**
     * Registers (or replaces) a template under the given name.
     *
     * @param Template|string $template
     */
    public function add(string $name, $template): self
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Template name must not be empty.');
        }

        $this->templates[$name] = Template::from($template);

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * @throws InvalidArgumentException when the template is not registered
     */
    public function get(string $name): Template
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('Template "%s" is not registered.', $name));
        }

        return $this->templates[$name];
    }

    /**
     * Shortcut for get($name)->render($parameters).
     *
     * @param array<string, mixed> $parameters
     */
    public function render(string $name, array $parameters = []): string
    {
        return $this->get($name)->render($parameters);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->templates);
    }

    /**
     * @return \ArrayIterator<string, Template>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->templates);
    }

    public function count(): int
    {
        return count($this->templates);
    }
}
