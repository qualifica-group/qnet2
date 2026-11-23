<?php

declare(strict_types=1);

namespace Integrations\Formalab;

use App\Integrations\IntegrationRegistry;
use App\Models\WorkOrder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Integrations\Formalab\Console\SyncClassesCommand;
use Integrations\Formalab\Health\FormalabHealthCheck;
use Integrations\Formalab\Jobs\SyncFormalabClasses;
use Integrations\Formalab\Listeners\FollowClassSelect;
use Integrations\Formalab\Models\FormalabCallLog;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\MappedFieldLock;
use Integrations\Formalab\Support\Trigger;
use Integrations\Formalab\Tables\FormalabCallLogsTableDefinition;
use Integrations\Formalab\Tables\FormalabClassesTableDefinition;

/**
 * Formalab integration (spec 0422, 0423, 0426), isolated from the core. Disabled
 * (FORMALAB_ENABLED=false, the default) it only loads its migrations and
 * translations: no route, menu entry, health check, table, command or
 * schedule exists. The state is read at boot, so a change needs
 * `php artisan config:cache` (and `route:cache` where routes are cached).
 */
class FormalabServiceProvider extends ServiceProvider
{
    public const string KEY = 'formalab';

    /**
     * Top-level section shared by the external integrations (spec 0426 D-8),
     * created by the first one that boots, just before Develop.
     */
    private const array NAVIGATION_SECTION = [
        'key' => 'external-integrations',
        'label' => 'integrations:nav.externalIntegrations',
        'icon' => null,
        'route' => null,
        'permission' => null,
        'type' => 'section',
    ];

    private const string NAVIGATION_SECTION_BEFORE = 'develop';

    private const string SUPER_ADMIN = 'super-admin';

    /** The Formalab group: status page and class catalogue, super-admin only. */
    private const array NAVIGATION_GROUP = [
        'key' => 'formalab-integration',
        'label' => 'formalab:nav.label',
        'icon' => 'graduation-cap',
        'route' => null,
        'permission' => null,
        'children' => [
            ['key' => self::KEY, 'label' => 'formalab:nav.status', 'icon' => 'activity', 'route' => '/dev/formalab', 'permission' => null, 'role' => self::SUPER_ADMIN],
            ['key' => 'formalab-classes', 'label' => 'formalab:nav.classes', 'icon' => 'library', 'route' => '/dev/formalab/classes', 'permission' => null, 'role' => self::SUPER_ADMIN],
        ],
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
        $this->registerNavigation();
        $this->registerTableDefinitions();
        $this->registerClassRelationTarget();
        $this->app->tag([FormalabHealthCheck::class], 'system-health.checks');
        $this->app->tag([MappedFieldLock::class], 'work-orders.attribute-locks');
        WorkOrder::saved(fn (WorkOrder $workOrder) => $this->app->make(FollowClassSelect::class)->handle($workOrder));
        $this->app->make(IntegrationRegistry::class)->activate(self::KEY);
        $this->callAfterResolving(Schedule::class, $this->schedule(...));
        $this->commands([SyncClassesCommand::class]);
    }

    /**
     * Puts the Formalab group in the "Integrazioni esterne" section, creating
     * the section before Develop if no other integration did. Idempotent: with
     * a cached config the group is already there and is not added again.
     */
    private function registerNavigation(): void
    {
        $items = config('navigation.items', []);

        if (! in_array(self::NAVIGATION_SECTION['key'], array_column($items, 'key'), true)) {
            $position = array_search(self::NAVIGATION_SECTION_BEFORE, array_column($items, 'key'), true);
            array_splice($items, $position === false ? count($items) : $position, 0, [[...self::NAVIGATION_SECTION, 'children' => []]]);
        }

        $items = array_map(static function (array $section): array {
            if ($section['key'] === self::NAVIGATION_SECTION['key'] && ! in_array(self::NAVIGATION_GROUP['key'], array_column($section['children'], 'key'), true)) {
                $section['children'][] = self::NAVIGATION_GROUP;
            }

            return $section;
        }, $items);

        config(['navigation.items' => $items]);
    }

    private function registerTableDefinitions(): void
    {
        config(['tables.definitions' => [
            ...config('tables.definitions', []),
            'formalab-call-logs' => FormalabCallLogsTableDefinition::class,
            'formalab-classes' => FormalabClassesTableDefinition::class,
        ]]);
    }

    /** The work order class select is a relation field onto the catalogue (spec 0426 D-2). */
    private function registerClassRelationTarget(): void
    {
        config(['custom-fields.relation_only_targets' => [
            ...config('custom-fields.relation_only_targets', []),
            FormalabClass::ENTITY_TYPE => ['model' => FormalabClass::class, 'label' => 'formalab:classes.entity'],
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
