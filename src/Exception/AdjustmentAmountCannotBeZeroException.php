<?php

declare(strict_types=1);

namespace Alcor\Payroll\Exception;

use DomainException;

final class AdjustmentAmountCannotBeZeroException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'A manual adjustment must be a positive or negative amount; zero changes nothing but pollutes the audit trail.'
        );
    }
}
