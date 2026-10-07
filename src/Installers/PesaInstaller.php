<?php

declare(strict_types=1);

namespace Jengo\Pesa\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class PesaInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'pesa';
    }

    public static function description(): string
    {
        return 'Install Jengo Pesa payments subsystem, publish configuration, and run ledger migrations';
    }

    public static function reasonForSkipping(): string
    {
        return 'Pesa configuration is already published in app/Config/Pesa.php.';
    }

    public function shouldRun(): bool
    {
        return ! file_exists(APPPATH . 'Config/Pesa.php');
    }

    public function install(): void
    {
        $this->addRun();

        CLI::write('Installing Jengo Pesa payment subsystem...', 'cyan');

        $dest = APPPATH . 'Config/Pesa.php';
        $force = CLI::getOption('force') !== null;

        // 1. Publish Configuration file
        if (file_exists($dest) && ! $force) {
            CLI::write("Config/Pesa.php already exists at [{$dest}], skipping.", 'yellow');
        } else {
            $source = dirname(__DIR__) . '/Config/Pesa.php';
            $content = (string) file_get_contents($source);

            $content = str_replace(
                "namespace Jengo\\Pesa\\Config;\n\nuse CodeIgniter\\Config\\BaseConfig;",
                "namespace Config;\n\nuse Jengo\\Pesa\\Config\\Pesa as BasePesa;",
                $content
            );

            $content = str_replace(
                'class Pesa extends BaseConfig',
                'class Pesa extends BasePesa',
                $content
            );

            $this->writeFile($dest, $content);
            CLI::write('Published Config/Pesa.php successfully.', 'green');
        }

        // 2. Run Database Migrations for pesa_transactions table
        CLI::write('Running Jengo Pesa migrations...', 'cyan');
        try {
            command('migrate --all');
            CLI::write('Executed pesa migrations successfully.', 'green');
        } catch (\Throwable $e) {
            CLI::write('Note: Run `php spark migrate --all` manually if database is not yet connected.', 'yellow');
        }

        CLI::newLine();
        CLI::write('Jengo Pesa installation complete!', 'green');
    }
}
