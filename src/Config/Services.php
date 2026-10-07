<?php

declare(strict_types=1);

namespace Jengo\Pesa\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\PesaManager;

class Services extends BaseService
{
    public static function pesa(?PesaConfig $config = null, bool $getShared = true): PesaManager
    {
        if ($getShared) {
            return static::getSharedInstance('pesa', $config);
        }

        $config ??= config('Pesa') ?? new PesaConfig();

        return new PesaManager($config);
    }
}
