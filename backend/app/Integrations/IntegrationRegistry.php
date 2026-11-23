<?php

declare(strict_types=1);

namespace App\Integrations;

use Illuminate\Container\Attributes\Singleton;

/**
 * The optional integrations switched on for this installation. Each
 * integration's service provider activates itself at boot only when enabled,
 * so an installation that uses none exposes an empty list.
 */
#[Singleton]
final class IntegrationRegistry
{
    /** @var list<string> */
    private array $active = [];

    public function activate(string $key): void
    {
        if (! in_array($key, $this->active, true)) {
            $this->active[] = $key;
        }
    }

    /**
     * @return list<string>
     */
    public function active(): array
    {
        return $this->active;
    }
}
