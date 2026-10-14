<?php

declare(strict_types=1);

namespace Alcor\Payroll;

use Alcor\Payroll\Exception\MismatchedCurrenciesException;
use InvalidArgumentException;

/**
 * Immutable monetary amount stored in integer minor units (cents).
 *
 * Minor units are deliberate: float arithmetic on money silently loses or
 * invents fractions of a cent, which is exactly what a payroll line cannot
 * afford. Parsing accepts display strings ("$1,050.00", "-$45.55") and
 * rejects anything that cannot be represented in cents without rounding.
 */
final readonly class Money
{
    public const string DEFAULT_CURRENCY = 'USD';

    private const array SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    private const string PATTERN = '/^([+-]?)(?:(\$|€|£)\s*)?(\d{1,3}(?:,\d{3})*|\d+)(?:\.(\d{1,2}))?$/u';

    public function __construct(
        public int $cents,
        public string $currency = self::DEFAULT_CURRENCY,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Currency "%s" must be an uppercase ISO 4217 three-letter code.', $currency)
            );
        }
    }

    public static function fromCents(int $cents, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self($cents, $currency);
    }

    public static function fromDecimalString(string $decimal, string $currency = self::DEFAULT_CURRENCY): self
    {
        if (preg_match(self::PATTERN, trim($decimal), $matches) !== 1) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a monetary amount in decimal notation.', $decimal)
            );
        }

        [$symbol, $whole, $fraction] = [$matches[2], $matches[3], $matches[4] ?? ''];

        if ($symbol !== '' && $symbol !== (self::SYMBOLS[$currency] ?? '')) {
            throw new InvalidArgumentException(
                sprintf('Amount "%s" carries a symbol that does not match currency %s.', $decimal, $currency)
            );
        }

        $cents = (int) str_replace(',', '', $whole) * 100 + (int) str_pad($fraction, 2, '0');

        return new self($matches[1] === '-' ? -$cents : $cents, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->cents, $this->currency);
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

    /**
     * Human-readable form for audit output, e.g. "$1,104.45" or "-$45.55".
     */
    public function format(): string
    {
        $absolute = abs($this->cents);
        $body = sprintf(
            '%s%s.%02d',
            self::SYMBOLS[$this->currency] ?? $this->currency . ' ',
            number_format(intdiv($absolute, 100), 0, '', ','),
            $absolute % 100,
        );

        return $this->cents < 0 ? '-' . $body : $body;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw MismatchedCurrenciesException::between($this->currency, $other->currency);
        }
    }
}
