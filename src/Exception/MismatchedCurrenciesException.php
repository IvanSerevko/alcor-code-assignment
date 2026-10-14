<?php

declare(strict_types=1);

namespace Alcor\Payroll\Exception;

use DomainException;

final class MismatchedCurrenciesException extends DomainException
{
    public static function between(string $left, string $right): self
    {
        return new self(
            sprintf('Cannot combine amounts of different currencies: %s vs %s.', $left, $right)
        );
    }
}
