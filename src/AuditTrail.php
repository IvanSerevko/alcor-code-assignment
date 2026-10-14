<?php

declare(strict_types=1);

namespace Alcor\Payroll;

use InvalidArgumentException;

/** Frozen system value + ordered adjustments; current value is derived, never stored. */
final readonly class AuditTrail
{
    /** @var list<ManualAdjustment> */
    public array $adjustments;

    /** @param list<ManualAdjustment> $adjustments */
    public function __construct(
        public Money $systemValue,
        array $adjustments,
    ) {
        foreach ($adjustments as $index => $adjustment) {
            if ($adjustment->ordinal !== $index + 1) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Audit trail must contain consecutive ordinals starting at 1, got %d at position %d.',
                        $adjustment->ordinal,
                        $index + 1,
                    )
                );
            }
        }

        $this->adjustments = $adjustments;
    }

    public function currentValue(): Money
    {
        $value = $this->systemValue;

        foreach ($this->adjustments as $adjustment) {
            $value = $value->plus($adjustment->amount);
        }

        return $value;
    }
}
