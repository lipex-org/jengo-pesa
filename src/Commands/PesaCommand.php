<?php

declare(strict_types=1);

namespace Jengo\Pesa\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

/**
 * Master command for Jengo Pesa payment utilities.
 */
class PesaCommand extends AbstractMasterCommand
{
    protected $group = 'Jengo';
    protected $name = 'jengo:pesa';
    protected $description = 'Consolidated payment management and gateway tooling.';
    protected $usage = 'jengo:pesa <variant> [arguments] [options]';

    protected string $variantPath = 'Commands/Variants/Pesa';
}
