<?php

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fal\Stick\Cli;

use Fal\Stick\Fw;

/**
 * Console command.
 *
 * @author Eko Kurniawan <ekokurniawanbs@gmail.com>
 */
class Command
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var callable
     */
    protected $code;

    /**
     * @var array
     */
    protected $arguments = array();

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var string
     */
    protected $help = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * Create command.
     *
     * @param string   $name
     * @param callable $name
     * @param string   $description
     *
     * @return Command
     */
    public static function create(
        string $name,
        callable $code,
        string $description = null
    ): Command {
        return (new static($name))->setCode($code);
    }

    /**
     * Class constructor.
     *
     * @param string $name
     * @param string $description
     */
    public function __construct(string $name = null, string $description = null)
    {
        $this->setName(
            $this->name ?? $name ?? preg_replace(
                '/_command$/',
                '',
                Fw::snakeCase(Fw::classname($this))
            )
        );
        $this->setDescription($description ?? '');
        $this->configure();
    }

    /**
     * Returns the command name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the command name.
     *
     * @param string $name
     *
     * @return Command
     */
    public function setName(string $name): Command
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Returns handler.
     *
     * @return callable|null
     */
    public function getCode(): ?callable
    {
        return $this->code;
    }

    /**
     * Sets handler.
     *
     * @param callable $code
     *
     * @return Command
     */
    public function setCode(callable $code): Command
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Returns the command arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Sets the command argument.
     *
     * @param string      $name
     * @param string|null $description
     * @param mixed       $defaultValue
     * @param bool        $required
     *
     * @return Command
     */
    public function addArgument(string $name, string $description = null, $defaultValue = null, bool $required = false): Command
    {
        if (!preg_match('/^\w+$/', $name)) {
            throw new \LogicException(sprintf('Invalid argument name: %s.', $name));
        }

        $this->arguments[$name] = array($description, $defaultValue, $required);

        return $this;
    }

    /**
     * Returns the command options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Sets the command option.
     *
     * @param string      $name
     * @param string|null $description
     * @param string|null $alias
     * @param mixed       $defaultValue
     * @param bool        $required
     *
     * @return Command
     */
    public function addOption(string $name, string $description = null, string $alias = null, $defaultValue = null, bool $required = false): Command
    {
        $this->options[$name] = array($description, $alias, $defaultValue, $required);

        return $this;
    }

    /**
     * Returns the command help.
     *
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * Sets the command help.
     *
     * @param string $help
     *
     * @return Command
     */
    public function setHelp(string $help): Command
    {
        $this->help = $help;

        return $this;
    }

    /**
     * Returns the command description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the command description.
     *
     * @param string $description
     *
     * @return Command
     */
    public function setDescription(string $description): Command
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Run this command.
     *
     * @param Console $console
     * @param Input   $input
     *
     * @return int
     */
    public function run(Console $console, Input $input): int
    {
        $statusCode = $this->code ? ($this->code)($console, $input, $this) : $this->execute($console, $input);

        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
    }

    /**
     * Command logic.
     *
     * @param Console $console
     * @param Input   $input
     *
     * @return mixed
     */
    protected function execute(Console $console, Input $input)
    {
        throw new \LogicException('You must override the execute() method in the concrete command class.');
    }
}
