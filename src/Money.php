<?php

declare(strict_types=1);

namespace Alcor\Payroll;

use InvalidArgumentException;

/**
 * Immutable monetary amount in integer cents. Floats are off-limits:
 * a payroll line cannot afford drifting fractions of a cent.
 */
final readonly class Money
{
    private const string PATTERN = '/^([+-]?)(?:\$\s*)?(\d{1,3}(?:,\d{3})*|\d+)(?:\.(\d{1,2}))?$/';

    public function __construct(
        public int $cents,
    ) {
    }

    public static function fromDecimalString(string $decimal): self
    {
        if (preg_match(self::PATTERN, trim($decimal), $matches) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a monetary amount in decimal notation.', $decimal)
            );
        }

        $cents = (int) str_replace(',', '', $matches[2]) * 100
            + (int) str_pad($matches[3] ?? '', 2, '0');

        return new self($matches[1] === '-' ? -$cents : $cents);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function negate(): self
    {
        return new self(-$this->cents);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function toDecimalString(): string
    {
        $absolute = abs($this->cents);

        return sprintf(
            '%s%d.%02d',
            $this->cents < 0 ? '-' : '',
            intdiv($absolute, 100),
            $absolute % 100,
        );
    }

    public function format(): string
    {
        $absolute = abs($this->cents);
        $body = sprintf(
            '$%s.%02d',
            number_format(intdiv($absolute, 100), 0, '', ','),
            $absolute % 100,
        );

        return $this->cents < 0 ? '-' . $body : $body;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
