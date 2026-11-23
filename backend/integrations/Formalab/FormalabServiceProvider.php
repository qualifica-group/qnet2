<?php

declare(strict_types=1);

namespace Integrations\Formalab;

use App\Integrations\IntegrationRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Integrations\Formalab\Console\LinkWorkOrdersCommand;
use Integrations\Formalab\Console\SyncClassesCommand;
use Integrations\Formalab\Health\FormalabHealthCheck;
use Integrations\Formalab\Jobs\SyncFormalabClasses;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\MappedFieldLock;
use Integrations\Formalab\Support\Trigger;
use Integrations\Formalab\Tables\FormalabCallLogsTableDefinition;

/**
 * Formalab integration (spec 0422), isolated from the core. Disabled
 * (FORMALAB_ENABLED=false, the default) it only loads its migrations and
 * translations: no route, menu entry, health check, table, command or
 * schedule exists. The state is read at boot, so a change needs
 * `php artisan config:cache` (and `route:cache` where routes are cached).
 */
class FormalabServiceProvider extends ServiceProvider
{
    public const string KEY = 'formalab';

    private const string NAVIGATION_SECTION = 'develop';

    /** Shared group of the external-service integrations inside Develop. */
    private const array NAVIGATION_GROUP = [
        'key' => 'external-services',
        'label' => 'integrations:nav.externalServices',
        'icon' => 'plug',
        'route' => null,
        'permission' => null,
    ];

    private const int MAX_CRON_MINUTES = 59;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/formalab.php', self::KEY);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/lang', self::KEY);

        if (! config('formalab.enabled')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        $this->registerNavigationItem();
        $this->registerTableDefinition();
        $this->app->tag([FormalabHealthCheck::class], 'system-health.checks');
        $this->app->tag([MappedFieldLock::class], 'work-orders.attribute-locks');
        $this->app->make(IntegrationRegistry::class)->activate(self::KEY);
        $this->callAfterResolving(Schedule::class, $this->schedule(...));
        $this->commands([SyncClassesCommand::class, LinkWorkOrdersCommand::class]);
    }

    /**
     * Puts the status page under Develop > Servizi esterni (super-admin
     * only), creating the group if no other integration did. Idempotent:
     * with a cached config the item is already there and is not added again.
     */
    private function registerNavigationItem(): void
    {
        $item = [
            'key' => self::KEY,
            'label' => 'formalab:nav.label',
            'icon' => 'graduation-cap',
            'route' => '/dev/formalab',
            'permission' => null,
            'role' => 'super-admin',
        ];

        $items = array_map(function (array $section) use ($item): array {
            if (($section['key'] ?? null) !== self::NAVIGATION_SECTION) {
                return $section;
            }

            $section['children'] = $this->withItemInGroup($section['children'] ?? [], $item);

            return $section;
        }, config('navigation.items', []));

        config(['navigation.items' => $items]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<string, mixed>  $item
     * @return array<int, array<string, mixed>>
     */
    private function withItemInGroup(array $children, array $item): array
    {
        $keys = array_column($children, 'key');

        if (! in_array(self::NAVIGATION_GROUP['key'], $keys, true)) {
            $children[] = [...self::NAVIGATION_GROUP, 'children' => []];
        }

        return array_map(static function (array $child) use ($item): array {
            if ($child['key'] === self::NAVIGATION_GROUP['key'] && ! in_array($item['key'], array_column($child['children'], 'key'), true)) {
                $child['children'][] = $item;
            }

            return $child;
        }, $children);
    }

    private function registerTableDefinition(): void
    {
        config(['tables.definitions' => [
            ...config('tables.definitions', []),
            'formalab-call-logs' => FormalabCallLogsTableDefinition::class,
        ]]);
    }

    private function schedule(Schedule $schedule): void
    {
        $minutes = max(1, min(self::MAX_CRON_MINUTES, (int) config('formalab.sync_interval_minutes')));

        $schedule->job(new SyncFormalabClasses(Trigger::Schedule))
            ->name('formalab:sync-classes')
            ->cron("*/{$minutes} * * * *")
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('model:prune', ['--model' => [FormalabCallLog::class, FormalabSyncRun::class]])
            ->name('formalab:prune')
            ->daily()
            ->onOneServer();
    }
}
