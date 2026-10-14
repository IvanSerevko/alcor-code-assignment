<?php

declare(strict_types=1);

namespace Alcor\Payroll;

use Alcor\Payroll\Exception\AdjustmentAmountCannotBeZeroException;
use Alcor\Payroll\Exception\AdjustmentCommentCannotBeBlankException;
use InvalidArgumentException;

/**
 * One recorded correction: ordinal, signed non-zero amount, mandatory comment.
 * Immutable and self-validating.
 */
final readonly class ManualAdjustment
{
    public function __construct(
        public int $ordinal,
        public Money $amount,
        public string $comment,
    ) {
        if ($ordinal < 1) {
            throw new InvalidArgumentException(
                sprintf('Adjustment ordinal must be 1 or greater, got %d.', $ordinal)
            );
        }

        if ($amount->isZero()) {
            throw new AdjustmentAmountCannotBeZeroException();
        }

        if (trim($this->comment) === '') {
            throw new AdjustmentCommentCannotBeBlankException();
        }
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }
}
