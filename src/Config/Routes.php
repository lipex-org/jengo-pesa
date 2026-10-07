<?php

declare(strict_types=1);

namespace Jengo\Pesa\Config;

use CodeIgniter\Router\RouteCollection;
use Jengo\Pesa\Controllers\PesaWebhookController;

/**
 * Pesa Routes registration.
 */
if (isset($routes) && $routes instanceof RouteCollection) {
    $routes->group('pesa', static function ($routes) {
        $routes->match(['get', 'post'], 'webhook/(:segment)', [PesaWebhookController::class, 'handle'], ['as' => 'pesa.webhook', 'csrf' => false]);
    });
}
