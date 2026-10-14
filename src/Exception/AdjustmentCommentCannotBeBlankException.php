<?php

declare(strict_types=1);

namespace Alcor\Payroll\Exception;

use DomainException;

final class AdjustmentCommentCannotBeBlankException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'A manual adjustment requires a comment explaining why the correction was made.'
        );
    }
}
