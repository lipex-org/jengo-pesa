<?php

declare(strict_types=1);

namespace Jengo\Pesa;

use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\Contracts\GatewayInterface;
use Jengo\Pesa\Drivers\Fake\FakeGateway;
use Jengo\Pesa\Drivers\Mpesa\MpesaGateway;
use Jengo\Pesa\Drivers\Pesapal\PesapalGateway;
use Jengo\Pesa\Drivers\Stripe\StripeGateway;
use Jengo\Pesa\Exceptions\InvalidGatewayException;

class PesaManager
{
    /**
     * @var array<string, GatewayInterface>
     */
    protected array $drivers = [];

    /**
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    public function __construct(protected PesaConfig $config)
    {
    }

    /**
     * Resolve a gateway instance by name or return the default.
     */
    public function gateway(?string $name = null): GatewayInterface
    {
        $name = $name ?? $this->config->default;

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = $this->resolve($name);
    }

    /**
     * Resolve and build a gateway driver.
     */
    protected function resolve(string $name): GatewayInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->config);
        }

        $gatewayConfig = $this->config->gateways[$name] ?? [];

        return match ($name) {
            'mpesa'       => new MpesaGateway($gatewayConfig, $this->config),
            'pesapal'     => new PesapalGateway($gatewayConfig, $this->config),
            'stripe'      => new StripeGateway($gatewayConfig, $this->config),
            'fake'        => new FakeGateway($gatewayConfig, $this->config),
            default       => throw new InvalidGatewayException("Unsupported payment gateway driver: [{$name}]"),
        };
    }

    /**
     * Register a custom gateway driver.
     */
    public function extend(string $driver, callable $callback): static
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Dynamically call methods on default gateway driver.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->gateway()->{$method}(...$parameters);
    }
}
