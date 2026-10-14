<?php

declare(strict_types=1);

namespace Alcor\Payroll;

use Countable;
use InvalidArgumentException;

/**
 * Immutable projection of a line: frozen system value, ordered adjustments,
 * and the current value - always derived, never stored, so it cannot drift.
 */
final readonly class AuditTrail implements Countable
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

    public function hasAdjustments(): bool
    {
        return $this->adjustments !== [];
    }

    public function count(): int
    {
        return count($this->adjustments);
    }
}
