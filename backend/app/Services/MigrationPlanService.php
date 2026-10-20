<?php

namespace App\Services;

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationPlan;

/**
 * Reads and persists the app-wide mass-import plan (spec 0046): the ordered set
 * of migration sources (spec 0013) the "Import all" run executes. The plan is a
 * singleton row; when absent, the default is App\Migrations\MigrationOrder::PHASES
 * flattened (every registered source, enabled). `current()` always reconciles the
 * stored plan against the live registry so a source added to / removed from
 * config/migrations.php is never silently dropped or left orphaned, and a source
 * this installation may not import (spec 0285, D-5) is never enabled.
 */
class MigrationPlanService
{
    public function __construct(private readonly MigrationRegistry $registry) {}

    /**
     * The reconciled plan: ordered list of ['source' => key, 'enabled' => bool].
     *
     * @return list<array{source: string, enabled: bool}>
     */
    public function current(): array
    {
        $stored = MigrationPlan::query()->first()?->sources;

        return $this->reconcile(is_array($stored) ? $stored : $this->default());
    }

    /**
     * The enabled source keys in order — exactly what a mass run executes.
     *
     * @return list<string>
     */
    public function enabledSources(): array
    {
        return array_values(array_map(
            static fn (array $item): string => $item['source'],
            array_filter($this->current(), static fn (array $item): bool => $item['enabled']),
        ));
    }

    /**
     * Upsert the singleton plan (last save wins).
     *
     * @param  list<array{source: string, enabled: bool}>  $sources
     */
    public function save(array $sources): MigrationPlan
    {
        $plan = MigrationPlan::query()->first();

        if ($plan !== null) {
            $plan->update(['sources' => array_values($sources)]);

            return $plan;
        }

        return MigrationPlan::query()->create(['sources' => array_values($sources)]);
    }

    /**
     * Registered source keys in MigrationOrder phase order: the dependency order
     * first, then any registered source not listed there (a newly added source
     * lands at the end), deduped and filtered to what is actually registered.
     *
     * @return list<string>
     */
    private function orderedRegisteredKeys(): array
    {
        /** @var list<string> $registered */
        $registered = array_keys((array) config('migrations.definitions', []));

        $ordered = array_merge(...MigrationOrder::phases());
        $merged = array_values(array_unique([...$ordered, ...$registered]));

        return array_values(array_filter(
            $merged,
            static fn (string $key): bool => in_array($key, $registered, true),
        ));
    }

    /**
     * Default plan: every registered source, enabled, in dependency order.
     *
     * @return list<array{source: string, enabled: bool}>
     */
    private function default(): array
    {
        return array_map(
            static fn (string $key): array => ['source' => $key, 'enabled' => true],
            $this->orderedRegisteredKeys(),
        );
    }

    /**
     * Keep the stored order, drop keys no longer registered, and append any
     * registered source missing from the stored plan (enabled) in dependency
     * order — a new source is never silently excluded. An unavailable source
     * reads as disabled; its stored flag is kept for when it becomes available.
     *
     * @param  array<int, mixed>  $stored
     * @return list<array{source: string, enabled: bool}>
     */
    private function reconcile(array $stored): array
    {
        $registered = $this->orderedRegisteredKeys();
        $seen = [];
        $result = [];

        foreach ($stored as $item) {
            $key = is_array($item) ? ($item['source'] ?? null) : null;

            if (! is_string($key) || ! in_array($key, $registered, true) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = ['source' => $key, 'enabled' => (bool) ($item['enabled'] ?? true) && $this->isAvailable($key)];
        }

        foreach ($registered as $key) {
            if (! isset($seen[$key])) {
                $result[] = ['source' => $key, 'enabled' => $this->isAvailable($key)];
            }
        }

        return $result;
    }

    private function isAvailable(string $key): bool
    {
        return $this->registry->resolve($key)->isAvailable();
    }
}
