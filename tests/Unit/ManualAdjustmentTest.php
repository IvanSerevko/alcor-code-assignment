<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit;

use Alcor\Payroll\Exception\AdjustmentAmountCannotBeZeroException;
use Alcor\Payroll\Exception\AdjustmentCommentCannotBeBlankException;
use Alcor\Payroll\ManualAdjustment;
use Alcor\Payroll\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManualAdjustment::class)]
final class ManualAdjustmentTest extends TestCase
{
    public function testRecordsSignedAmountWithReason(): void
    {
        $adjustment = new ManualAdjustment(
            3,
            Money::fromDecimalString('-0.10'),
            'Minor rounding adjustment',
        );

        self::assertSame(3, $adjustment->ordinal);
        self::assertSame(-10, $adjustment->amount->cents);
        self::assertTrue($adjustment->isNegative());
    }

    public function testAcceptsPositiveCompensatingAdjustment(): void
    {
        $adjustment = new ManualAdjustment(
            5,
            Money::fromDecimalString('+$0.20'),
            'Correcting mistake in adjustment #4',
        );

        self::assertSame(20, $adjustment->amount->cents);
        self::assertFalse($adjustment->isNegative());
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(AdjustmentAmountCannotBeZeroException::class);

        new ManualAdjustment(1, Money::fromCents(0), 'No-op');
    }

    public function testRejectsBlankComment(): void
    {
        $this->expectException(AdjustmentCommentCannotBeBlankException::class);

        new ManualAdjustment(1, Money::fromDecimalString('10.00'), '   ');
    }

    public function testRejectsEmptyComment(): void
    {
        $this->expectException(AdjustmentCommentCannotBeBlankException::class);

        new ManualAdjustment(1, Money::fromDecimalString('10.00'), '');
    }

    public function testRejectsOrdinalBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ManualAdjustment(0, Money::fromDecimalString('10.00'), 'Why');
    }
}
