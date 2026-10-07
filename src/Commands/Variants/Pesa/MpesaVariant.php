<?php

declare(strict_types=1);

namespace Jengo\Pesa\Commands\Variants\Pesa;

use Jengo\Base\Commands\Core\AbstractNestedVariant;

class MpesaVariant extends AbstractNestedVariant
{
    protected string $variantPath = 'Commands/Variants/Pesa/Mpesa';

    public static function name(): string
    {
        return 'mpesa';
    }

    public static function description(): string
    {
        return 'M-Pesa Daraja payment commands and utilities.';
    }
}
