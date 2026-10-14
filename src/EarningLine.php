<?php

declare(strict_types=1);

namespace Alcor\Payroll;

/**
 * Invariant: manual corrections outrank system recalculation; history is append-only.
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

    /** Returns false when ignored: manual corrections take precedence. */
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
