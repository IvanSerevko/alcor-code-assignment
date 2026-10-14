<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit;

use Alcor\Payroll\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
final class MoneyTest extends TestCase
{
    public static function decimalAmounts(): array
    {
        return [
            'whole dollars' => ['1050.00', 105000],
            'no cents' => ['1000', 100000],
            'single cent' => ['0.01', 1],
            'negative with symbol' => ['-45.55', -4555],
            'negative with usd sign' => ['-$45.55', -4555],
            'positive with plus sign' => ['+100.10', 10010],
            'positive with usd sign' => ['+$100.10', 10010],
            'thousands separator' => ['$1,104.45', 110445],
            'one cent with symbol' => ['$0.10', 10],
        ];
    }

    #[DataProvider('decimalAmounts')]
    public function testParsesDecimalAmountsToExactCents(string $input, int $expectedCents): void
    {
        self::assertSame($expectedCents, Money::fromDecimalString($input)->cents);
    }

    public static function invalidDecimalAmounts(): array
    {
        return [
            'empty string' => [''],
            'letters' => ['abc'],
            'sub-cent precision' => ['10.555'],
            'missing whole part' => ['.50'],
            'double decimal point' => ['1.2.3'],
            'misplaced separator' => ['1,10.45'],
            'symbol in the middle' => ['4$5.55'],
            'spaced apart sign' => ['- 45.55'],
        ];
    }

    #[DataProvider('invalidDecimalAmounts')]
    public function testRejectsAmountsThatAreNotRepresentableInCents(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString($input);
    }

    public function testSubtractingAdjustmentKeepsExactCentPrecision(): void
    {
        $base = Money::fromDecimalString('1050.00');
        $adjustment = Money::fromDecimalString('-45.55');

        self::assertSame(100445, $base->plus($adjustment)->cents);
        self::assertTrue($base->plus($adjustment)->equals(Money::fromDecimalString('1004.45')));
    }

    public function testArithmeticNeverMutatesOperands(): void
    {
        $base = Money::fromDecimalString('1050.00');
        $base->plus(Money::fromDecimalString('-45.55'));

        self::assertSame(105000, $base->cents);
    }

    public static function signClassification(): array
    {
        return [
            'zero' => [0, true, false],
            'negative' => [-1, false, false],
            'positive' => [1, false, true],
        ];
    }

    #[DataProvider('signClassification')]
    public function testSignClassification(int $cents, bool $isZero, bool $isPositive): void
    {
        $amount = new Money($cents);

        self::assertSame($isZero, $amount->isZero());
        self::assertSame($isPositive, $amount->isPositive());
    }

    public function testFormatsWithGroupingSignAndSymbol(): void
    {
        self::assertSame('$1,104.45', Money::fromDecimalString('1104.45')->format());
        self::assertSame('-$45.55', Money::fromDecimalString('-45.55')->format());
        self::assertSame('$1,050.00', Money::fromDecimalString('1050.00')->format());
    }
}
