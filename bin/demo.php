<?php

declare(strict_types=1);

use Alcor\Payroll\EarningLine;
use Alcor\Payroll\Money;

require __DIR__ . '/../vendor/autoload.php';

// CLI walkthrough of the worked example from the assignment brief.

$signed = static function (Money $amount): string {
    return ($amount->isPositive() ? '+' : '') . $amount->format();
};

$row = static function (int $step, string $amount, Money $value, string $description): void {
    printf("%-4d %-10s %12s  %s\n", $step, $amount, $value->format(), $description);
};

$line = EarningLine::fromSystemValue(Money::fromDecimalString('1000.00'));

echo "History of manual adjustments to an earning line\n\n";
printf("%-4s %-10s %12s  %s\n", 'Step', 'Amount', 'Current', 'Event');
printf("%s\n", str_repeat('-', 100));

$row(1, '—', $line->currentValue(), 'System calculates the line');

$line->recalculate(Money::fromDecimalString('1050.00'));
$row(2, '—', $line->currentValue(), 'Source data changes, system recalculates (no manual correction yet)');

$declined = Money::fromDecimalString('-45.55');
$line->applyAdjustment($declined, 'Employee declined dental benefit; reversing deduction');
$row(3, $signed($declined), $line->currentValue(), 'Manual correction; system value frozen at $1,050.00');

$line->recalculate(Money::fromDecimalString('1075.00'));
$row(4, '—', $line->currentValue(), 'System attempts to recalculate (ignored: line already manually corrected)');

$corrections = [
    [5, Money::fromDecimalString('100.10'), 'Late correction: missed approved overtime bonus'],
    [6, Money::fromDecimalString('-0.10'), 'Minor rounding adjustment'],
    [7, Money::fromDecimalString('-0.20'), 'Second minor rounding adjustment'],
    [8, Money::fromDecimalString('0.20'), 'Correcting mistake in adjustment #4'],
];

foreach ($corrections as [$step, $amount, $comment]) {
    $line->applyAdjustment($amount, $comment);
    $row($step, $signed($amount), $line->currentValue(), sprintf('Manual correction: "%s"', $comment));
}

$trail = $line->auditTrail();

echo "\nExpected audit history for this line\n\n";
printf("%-16s %12s  %s\n", 'Entry', 'Value', '');
printf("%s\n", str_repeat('-', 100));
printf("%-16s %12s  %s\n", 'System value', $trail->systemValue->format(), '(frozen at the first manual correction)');

foreach ($trail->adjustments as $adjustment) {
    printf(
        "%-16s %12s  %s\n",
        sprintf('Adjustment %d', $adjustment->ordinal),
        $signed($adjustment->amount),
        sprintf('"%s"', $adjustment->comment),
    );
}

printf("%-16s %12s  %s\n", 'Current value', $trail->currentValue()->format(), '');
