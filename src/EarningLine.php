<?php

declare(strict_types=1);

namespace Alcor\Payroll;

/**
 * Aggregate root for one earning line.
 *
 * Invariant: manual corrections permanently outrank system recalculation.
 * History is append-only - mistakes are fixed by compensating adjustments.
 */
final class EarningLine
{
    /** @var list<ManualAdjustment> */
    private array $adjustments = [];

    private function __construct(
        private Money $systemValue,
    ) {
    }

    public static function fromSystemValue(Money $systemValue): self
    {
        return new self($systemValue);
    }

    /**
     * Returns false when ignored because manual corrections take precedence.
     */
    public function recalculate(Money $newSystemValue): bool
    {
        if ($this->isFrozen()) {
            return false;
        }

        $this->systemValue = $newSystemValue;

        return true;
    }

    public function applyAdjustment(Money $amount, string $comment): ManualAdjustment
    {
        $adjustment = new ManualAdjustment(
            ordinal: count($this->adjustments) + 1,
            amount: $amount,
            comment: $comment,
        );

        $this->adjustments[] = $adjustment;

        return $adjustment;
    }

    public function currentValue(): Money
    {
        return $this->auditTrail()->currentValue();
    }

    public function auditTrail(): AuditTrail
    {
        return new AuditTrail($this->systemValue, $this->adjustments);
    }

    public function isFrozen(): bool
    {
        return $this->adjustments !== [];
    }
}
