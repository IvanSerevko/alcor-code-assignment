<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit;

use Alcor\Payroll\AuditTrail;
use Alcor\Payroll\ManualAdjustment;
use Alcor\Payroll\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditTrail::class)]
final class AuditTrailTest extends TestCase
{
    public function testCurrentValueEqualsSystemValueWhenNoAdjustmentsYet(): void
    {
        $trail = new AuditTrail(Money::fromDecimalString('1050.00'), []);

        self::assertTrue($trail->currentValue()->equals(Money::fromDecimalString('1050.00')));
    }

    public function testCurrentValueIsDerivedFromFrozenBaseAndAllAdjustments(): void
    {
        $trail = new AuditTrail(
            Money::fromDecimalString('1050.00'),
            [
                new ManualAdjustment(1, Money::fromDecimalString('-45.55'), 'Employee declined dental benefit; reversing deduction'),
                new ManualAdjustment(2, Money::fromDecimalString('100.10'), 'Late correction: missed approved overtime bonus'),
                new ManualAdjustment(3, Money::fromDecimalString('-0.10'), 'Minor rounding adjustment'),
                new ManualAdjustment(4, Money::fromDecimalString('-0.20'), 'Second minor rounding adjustment'),
                new ManualAdjustment(5, Money::fromDecimalString('0.20'), 'Correcting mistake in adjustment #4'),
            ],
        );

        self::assertTrue($trail->currentValue()->equals(Money::fromDecimalString('1104.45')));
    }

    public function testRejectsTrailWithOrdinalGaps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditTrail(
            Money::fromDecimalString('100.00'),
            [
                new ManualAdjustment(1, Money::fromDecimalString('-1.00'), 'First'),
                new ManualAdjustment(3, Money::fromDecimalString('2.00'), 'Third without second'),
            ],
        );
    }

    public function testExposesAdjustmentsInRecordingOrder(): void
    {
        $trail = new AuditTrail(
            Money::fromDecimalString('100.00'),
            [
                new ManualAdjustment(1, Money::fromDecimalString('-1.00'), 'First'),
                new ManualAdjustment(2, Money::fromDecimalString('2.00'), 'Second'),
            ],
        );

        self::assertSame(['First', 'Second'], array_map(
            static fn (ManualAdjustment $a): string => $a->comment,
            $trail->adjustments,
        ));
    }
}
