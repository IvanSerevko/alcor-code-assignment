<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Scenario;

use Alcor\Payroll\EarningLine;
use Alcor\Payroll\ManualAdjustment;
use Alcor\Payroll\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** Reproduces the worked example from the assignment brief, steps 1-8. */
#[CoversClass(EarningLine::class)]
final class WorkedExampleTest extends TestCase
{
    public function testAssignmentScenarioProducesExpectedFinalValueAndAuditHistory(): void
    {
        $line = EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'));

        // Step 1: system calculates the line => $1,000.00
        self::assertSame(100000, $line->currentValue()->cents);

        // Step 2: source data changes, system recalculates (no manual correction yet) => $1,050.00
        self::assertTrue($line->recalculate(Money::fromDecimalString('1050.00')));
        self::assertSame(105000, $line->currentValue()->cents);

        // Step 3: specialist adds a manual correction => $1,004.45
        $line->applyAdjustment(
            Money::fromDecimalString('-45.55'),
            'Employee declined dental benefit; reversing deduction',
        );
        self::assertSame(100445, $line->currentValue()->cents);

        // Step 4: source data changes again, recalculation must be ignored => $1,004.45
        self::assertFalse($line->recalculate(Money::fromDecimalString('1100.00')));
        self::assertSame(100445, $line->currentValue()->cents);

        // Step 5: second correction => $1,104.55
        $line->applyAdjustment(Money::fromDecimalString('100.10'), 'Late correction: missed approved overtime bonus');
        self::assertSame(110455, $line->currentValue()->cents);

        // Step 6: third correction => $1,104.45
        $line->applyAdjustment(Money::fromDecimalString('-0.10'), 'Minor rounding adjustment');
        self::assertSame(110445, $line->currentValue()->cents);

        // Step 7: fourth correction => $1,104.25
        $line->applyAdjustment(Money::fromDecimalString('-0.20'), 'Second minor rounding adjustment');
        self::assertSame(110425, $line->currentValue()->cents);

        // Step 8: compensating correction for the mistake in step 7 => $1,104.45
        $line->applyAdjustment(Money::fromDecimalString('0.20'), 'Correcting mistake in adjustment #4');
        self::assertSame(110445, $line->currentValue()->cents);

        $trail = $line->auditTrail();

        self::assertSame('$1,050.00', $trail->systemValue->format());
        self::assertSame(
            ['-$45.55', '+$100.10', '-$0.10', '-$0.20', '+$0.20'],
            array_map(static fn (ManualAdjustment $a): string => self::signed($a->amount), $trail->adjustments),
        );
        self::assertSame('$1,104.45', $trail->currentValue()->format());
    }

    private static function signed(Money $amount): string
    {
        return ($amount->isPositive() ? '+' : '') . $amount->format();
    }
}
