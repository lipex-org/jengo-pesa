<?php

declare(strict_types=1);

namespace Jengo\Pesa\Commands\Variants\Pesa\Mpesa;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Jengo\Pesa\Contracts\C2BInterface;
use Jengo\Pesa\Pesa;

class RegisterC2BVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'register-c2b';
    }

    public static function description(): string
    {
        return 'Register Safaricom M-Pesa C2B Validation and Confirmation URLs with Daraja API.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--shortcode'    => 'Paybill or Buy Goods shortcode',
            '--type'         => 'Response type: Completed (default) or Cancelled',
            '--validation'   => 'Custom validation URL',
            '--confirmation' => 'Custom confirmation URL',
        ];
    }

    public function run(array $params): void
    {
        $shortcode = $this->getOption($params, 'shortcode') ?? config('Pesa')->gateways['mpesa']['shortcode'] ?? '';
        if (empty($shortcode)) {
            $shortcode = CLI::prompt('Enter M-Pesa Paybill / Till ShortCode');
        }

        $type = $this->getOption($params, 'type') ?? 'Completed';

        $baseUrl = rtrim(site_url(), '/');
        $valUrl = $this->getOption($params, 'validation') ?? ($baseUrl . '/pesa/webhook/mpesa');
        $confUrl = $this->getOption($params, 'confirmation') ?? ($baseUrl . '/pesa/webhook/mpesa');

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

    private function getOption(array $params, string $name): ?string
    {
        foreach ($params as $k => $v) {
            if (is_string($k) && $k === $name) {
                return (string) $v;
            }
            if (is_string($v) && strpos($v, "--{$name}=") === 0) {
                return substr($v, strlen("--{$name}="));
            }
        }

        $opt = CLI::getOption($name);
        return is_string($opt) && $opt !== '' ? $opt : null;
    }
}
