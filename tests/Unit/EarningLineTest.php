<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit;

use Alcor\Payroll\EarningLine;
use Alcor\Payroll\Exception\AdjustmentCommentCannotBeBlankException;
use Alcor\Payroll\Exception\MismatchedCurrenciesException;
use Alcor\Payroll\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass(EarningLine::class)]
final class EarningLineTest extends TestCase
{
    public function testNewLineCarriesItsSystemValue(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'));

        self::assertSame(100000, $line->currentValue()->cents);
        self::assertFalse($line->isFrozen());
    }

    public function testRecalculationIsAcceptedWhileNoManualCorrectionExists(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'));

        self::assertTrue($line->recalculate(Money::fromDecimalString('1050.00')));
        self::assertSame(105000, $line->currentValue()->cents);
    }

    public function testFirstAdjustmentFreezesTheSystemValue(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1050.00'));
        $line->applyAdjustment(Money::fromDecimalString('-45.55'), 'Employee declined dental benefit; reversing deduction');

        self::assertTrue($line->isFrozen());
        self::assertSame(105000, $line->auditTrail()->systemValue->cents);
        self::assertSame(100445, $line->currentValue()->cents);
    }

    public function testRecalculationIsIgnoredAfterManualCorrection(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1050.00'));
        $line->applyAdjustment(Money::fromDecimalString('-45.55'), 'Declined benefit');

        self::assertFalse($line->recalculate(Money::fromDecimalString('9999.00')));
        self::assertSame(100445, $line->currentValue()->cents);
    }

    public function testAdjustmentsAreAppendedWithConsecutiveOrdinals(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1050.00'));
        $first = $line->applyAdjustment(Money::fromDecimalString('-45.55'), 'Declined benefit');
        $second = $line->applyAdjustment(Money::fromDecimalString('100.10'), 'Missed overtime bonus');

        self::assertSame([1, 2], [$first->ordinal, $second->ordinal]);
        self::assertSame(110455, $line->currentValue()->cents);
    }

    public function testCompensatingAdjustmentFixesPreviousMistakeWithoutTouchingHistory(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1104.55'));
        $line->applyAdjustment(Money::fromDecimalString('-0.10'), 'Minor rounding adjustment');
        $line->applyAdjustment(Money::fromDecimalString('-0.20'), 'Second minor rounding adjustment');
        $line->applyAdjustment(Money::fromDecimalString('0.20'), 'Correcting mistake in adjustment #4');

        self::assertSame(110445, $line->currentValue()->cents);
        self::assertCount(3, $line->auditTrail()->adjustments);
        self::assertSame(-20, $line->auditTrail()->adjustments[1]->amount->cents);
    }

    public function testRejectsAdjustmentWithBlankComment(): void
    {
        $this->expectException(AdjustmentCommentCannotBeBlankException::class);

        EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'))
            ->applyAdjustment(Money::fromDecimalString('10.00'), ' ');
    }

    public function testRejectsAdjustmentInDifferentCurrency(): void
    {
        $this->expectException(MismatchedCurrenciesException::class);

        EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'))
            ->applyAdjustment(Money::fromCents(500, 'EUR'), 'Wrong currency');
    }

    public function testRejectsRecalculationInDifferentCurrency(): void
    {
        $this->expectException(MismatchedCurrenciesException::class);

        EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'))
            ->recalculate(Money::fromCents(500, 'EUR'));
    }

    public function testExposesOnlyAppendOnlyOperations(): void
    {
        $publicApi = array_map(
            static fn ($method): string => $method->getName(),
            (new ReflectionClass(EarningLine::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        sort($publicApi);

        self::assertSame(
            ['applyAdjustment', 'auditTrail', 'currentValue', 'fromSystemValue', 'isFrozen', 'recalculate'],
            $publicApi,
            'The aggregate must not expose anything that could edit or delete recorded history.',
        );
    }
}
