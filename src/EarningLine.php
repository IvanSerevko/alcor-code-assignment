<?php

declare(strict_types=1);

namespace Alcor\Payroll;

use Alcor\Payroll\Exception\MismatchedCurrenciesException;

/**
 * Aggregate root for one earning line on a payslip.
 *
 * Central invariant of the payroll business case: the specialist's manual
 * corrections permanently outrank automatic system recalculation. From the
 * first adjustment onwards the line freezes its system value, recalculations
 * are ignored, and the value can only ever move through new append-only
 * adjustments. There is no API to edit or remove an adjustment — mistakes are
 * fixed by compensating adjustments, which keeps the audit history truthful.
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
     * Applies the system's recalculation, unless manual corrections have
     * taken precedence. Per the business rules this is ignored, not an error:
     * the caller learns via the return value whether anything was applied.
     */
    public function recalculate(Money $newSystemValue): bool
    {
        if ($this->isFrozen()) {
            return false;
        }

        $this->assertSameCurrency($newSystemValue);
        $this->systemValue = $newSystemValue;

        return true;
    }

    public function applyAdjustment(Money $amount, string $comment): ManualAdjustment
    {
        $this->assertSameCurrency($amount);

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

    private function assertSameCurrency(Money $amount): void
    {
        if ($amount->currency !== $this->systemValue->currency) {
            throw MismatchedCurrenciesException::between($this->systemValue->currency, $amount->currency);
        }
    }
}
