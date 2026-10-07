<?php

declare(strict_types=1);

namespace Jengo\Pesa\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Jengo\Pesa\Contracts\C2BInterface;
use Jengo\Pesa\Pesa;

class MpesaRegisterUrl extends BaseCommand
{
    protected $group = 'Pesa';
    protected $name = 'pesa:mpesa:register-c2b';
    protected $description = 'Register Safaricom M-Pesa C2B Validation and Confirmation URLs with Daraja API.';
    protected $usage = 'pesa:mpesa:register-c2b [options]';
    protected $options = [
        '--shortcode'    => 'Paybill or Buy Goods shortcode',
        '--type'         => 'Response type: Completed (default) or Cancelled',
        '--validation'   => 'Custom validation URL',
        '--confirmation' => 'Custom confirmation URL',
    ];

    public function run(array $params)
    {
        $shortcode = CLI::getOption('shortcode') ?? config('Pesa')->gateways['mpesa']['shortcode'] ?? '';
        if (empty($shortcode)) {
            $shortcode = CLI::prompt('Enter M-Pesa Paybill / Till ShortCode');
        }

        $type = CLI::getOption('type') ?? 'Completed';

        $baseUrl = rtrim(site_url(), '/');
        $valUrl = CLI::getOption('validation') ?? ($baseUrl . '/pesa/webhook/mpesa');
        $confUrl = CLI::getOption('confirmation') ?? ($baseUrl . '/pesa/webhook/mpesa');

        CLI::write("Registering C2B URLs for ShortCode: [{$shortcode}]...", 'yellow');
        CLI::write("Validation URL:   {$valUrl}");
        CLI::write("Confirmation URL: {$confUrl}");

        $gateway = Pesa::gateway('mpesa');
        if (! $gateway instanceof C2BInterface) {
            CLI::error('Mpesa gateway does not implement C2BInterface.');
            return;
        }

        try {
            $response = $gateway->registerC2BUrls($shortcode, $type, $valUrl, $confUrl);

            CLI::newLine();
            CLI::write('Safaricom Response:', 'green');
            CLI::write(json_encode($response, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            CLI::error('Registration failed: ' . $e->getMessage());
        }
    }
}
