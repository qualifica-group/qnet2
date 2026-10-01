<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Enums\RequestManagementReportRowMode;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\RequestDashboardRequest;
use App\Http\Resources\RequestManagementDashboardResource;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardBuilder;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use App\Services\RequestManagement\RequestManagementScope;
use App\Support\Cache\AggregateCache;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/request-management/report/dashboard (spec 0107, D-1): a
 * dedicated, SYNCHRONOUS endpoint (D-5 — no ExportRun, no queue, no
 * polling), deliberately not an extension of the generic stats framework
 * (spec 0026, D-1 — `StatsDefinition::widgets()` takes no parameters and
 * would need every one of its 14 domains touched for a need only this
 * module has). Same gate as the CSV (D-6) — same aggregates, no separate
 * grant — which spec 0185 made the "Statistiche Gestione Richieste" module's
 * own `RequestModule::STATISTICS_PERMISSION`.
 *
 * Spec 0130: the route carries its own RequestModule
 * (routes/api/request-management.php's own loop), resolved here via
 * RequestModule::fromRequest() — never from client input.
 */
class RequestManagementDashboardController extends BaseApiController
{
    public function __construct(private readonly RequestManagementDashboardBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(RequestDashboardRequest $request): JsonResponse
    {
        try {
            $module = RequestModule::fromRequest($request);
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can(RequestModule::STATISTICS_PERMISSION), 403);

            $dateFrom = $request->dateFrom();
            $dateTo = $request->dateTo();
            /** @var array<int, string> $categoryKeys */
            $categoryKeys = (array) $request->validated('category_keys');
            $rowMode = (string) $request->validated('row_mode');
            $operatorKeys = $request->operatorKeys();
            $siteKeys = $request->siteKeys();

            // The charts and tiles are the heavy part: cached per visibility
            // scope + validated parameters (spec 0178 D-5), as the plain array
            // the response already serialises. Auth and validation ran above.
            $payload = $this->cache->remember(
                $this->cacheKey($actor, $module, $dateFrom, $dateTo, $categoryKeys, $rowMode, $operatorKeys, $siteKeys),
                fn (): array => (new RequestManagementDashboardResource($this->builder->build(
                    $actor,
                    $dateFrom,
                    $dateTo,
                    $categoryKeys,
                    RequestManagementReportRowMode::from($rowMode),
                    ReportOperatorFilter::fromKeysOrAll($operatorKeys),
                    ReportSiteFilter::fromKeysOrAll($siteKeys),
                    $module,
                )))->resolve(),
                $actor,
            );

            return $this->ok([
                'applied' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'category_keys' => $categoryKeys,
                    'row_mode' => $rowMode,
                    // null echoes back "every operator" (spec 0108 D-2), so the
                    // client can tell an unfiltered response from a filtered one.
                    'operator_keys' => $operatorKeys,
                    // Same echo semantics for the Sede selection (spec 0112 D-4):
                    // null means "every Sede", i.e. an unfiltered response.
                    'site_keys' => $siteKeys,
                ],
                ...$payload,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * `rm-dash:{scope}:{sha1 of the validated parameters and locale}`: keys
     * are sorted and normalised so equivalent requests share one entry, and
     * the locale is part of it because labels are translated.
     *
     * @param  array<int, string>  $categoryKeys
     * @param  array<int, string>|null  $operatorKeys
     * @param  array<int, string>|null  $siteKeys
     */
    private function cacheKey(
        User $actor,
        RequestModule $module,
        ?string $dateFrom,
        ?string $dateTo,
        array $categoryKeys,
        string $rowMode,
        ?array $operatorKeys,
        ?array $siteKeys,
    ): string {
        $params = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'category_keys' => $this->sorted($categoryKeys),
            'row_mode' => $rowMode,
            'operator_keys' => $operatorKeys === null ? null : $this->sorted($operatorKeys),
            'site_keys' => $siteKeys === null ? null : $this->sorted($siteKeys),
            'locale' => app()->getLocale(),
        ];

        return 'rm-dash:'.RequestManagementScope::scopeFingerprint($actor, $module).':'.sha1((string) json_encode($params));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function sorted(array $keys): array
    {
        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}
