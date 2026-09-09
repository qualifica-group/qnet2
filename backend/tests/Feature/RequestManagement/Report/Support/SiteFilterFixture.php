<?php

declare(strict_types=1);

namespace Tests\Feature\RequestManagement\Report\Support;

use App\Enums\ExportFormat;
use App\Enums\RequestManagementReportRowMode;
use App\Models\BusinessFunction;
use App\Models\City;
use App\Models\EmploymentProfile;
use App\Models\Note;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use App\Services\RequestManagement\Report\ReportSiteFilter;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Permission;

/**
 * Shared fixture for the two spec 0112 test files (the Sede filter itself and
 * its endpoint/validation surface). A class rather than the guarded
 * `function_exists` helpers the 0106/0108 tests use: the two files need the
 * SAME builders, and duplicating them would let the two drift apart — which
 * is the one thing a filter spec cannot afford.
 */
final class SiteFilterFixture
{
    /**
     * The six report branches' categories, same tree the 0106/0108 tests build.
     *
     * @return array{gol: ProductCategory, consulenza: ProductCategory}
     */
    public static function categories(): array
    {
        $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
        $gol = ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']);
        ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']);
        $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza']);
        ProductCategory::factory()->create(['name' => 'APL']);

        return ['gol' => $gol, 'consulenza' => $consulenza];
    }

    /** A Sede identified by its address alone (it has no name column). */
    public static function site(string $line1, ?string $cityName = null): OperationalSite
    {
        $site = OperationalSite::factory()->create();

        $site->addresses()->create([
            'line1' => $line1,
            'is_primary' => true,
            'city_id' => $cityName === null ? null : City::factory()->create(['name' => $cityName])->id,
        ]);

        return $site->fresh(['addresses.city']);
    }

    /**
     * A GA2 with an employment profile and the given memberships — physical
     * and remote alike, the whole pivot (D-5). No membership at all when both
     * arguments are empty: the profile exists, the Sede does not (AC-006).
     *
     * @param  array<int, OperationalSite>  $remote
     */
    public static function operator(string $name, ?OperationalSite $physical = null, array $remote = []): User
    {
        $user = User::factory()->create(['name' => $name]);

        self::employ($user, $physical, $remote);

        return $user;
    }

    /**
     * @param  array<int, OperationalSite>  $remote
     */
    public static function employ(User $user, ?OperationalSite $physical = null, array $remote = []): void
    {
        $factory = EmploymentProfile::factory()->for($user, 'user');

        if ($physical !== null) {
            $factory = $factory->physicalSite($physical);
        }

        if ($remote !== []) {
            $factory = $factory->remoteSites(...$remote);
        }

        $factory->create();
    }

    /**
     * A request on the first workflow state with a callback due in range
     * ("richiami") plus one note written by its own GA2 ("telefonate", 0106
     * rev-3 D-17 — an unassigned request can never have one).
     */
    public static function quote(ProductCategory $category, ?int $operatorId): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
        $quote->forceFill(['operator_id' => $operatorId, 'next_callback_at' => now()])->save();

        Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $quote->opportunity_id,
            'created_at' => now(),
            ...($operatorId !== null ? ['user_id' => $operatorId] : []),
        ])->forceFill(['quote_id' => $quote->id])->save();

        return $quote;
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public static function actor(array $abilities = ['report', 'viewAll'], string $name = 'Attore Report'): User
    {
        foreach (['report', 'viewAll', 'viewSite'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $actor = User::factory()->create(['name' => $name]);

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("request-management.{$ability}");
        }

        return $actor;
    }

    /**
     * Sede A with Ada (2 GOL requests), Sede B with Bruno (1 GOL request):
     * the shape AC-001/AC-002/AC-003 address.
     *
     * @return array{a: OperationalSite, b: OperationalSite, ada: User, bruno: User, gol: ProductCategory}
     */
    public static function twoSites(): array
    {
        $categories = self::categories();
        $siteA = self::site('Via Alfa 1', 'Frattamaggiore');
        $siteB = self::site('Via Beta 2', 'Aversa');

        $ada = self::operator('Ada Rossi', $siteA);
        $bruno = self::operator('Bruno Verdi', $siteB);

        self::quote($categories['gol'], $ada->id);
        self::quote($categories['gol'], $ada->id);
        self::quote($categories['gol'], $bruno->id);

        return ['a' => $siteA, 'b' => $siteB, 'ada' => $ada, 'bruno' => $bruno, 'gol' => $categories['gol']];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function csv(
        User $actor,
        RequestManagementReportRowMode $rowMode,
        ?ReportSiteFilter $sites,
        ?ReportOperatorFilter $operators = null,
        string $file = 'site-filter.csv',
    ): array {
        app(RequestManagementReportGenerator::class)->generate(
            $actor,
            now()->subDay()->toDateString(),
            now()->addDay()->toDateString(),
            array_keys((array) config('request-management-report.branches')),
            $rowMode,
            ExportFormat::Csv,
            Storage::disk('local')->path($file),
            $operators,
            $sites,
        );

        return self::parse(Storage::disk('local')->get($file));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function parse(string $contents): array
    {
        return array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            array_filter(explode("\n", trim($contents, "\xEF\xBB\xBF\n"))),
        );
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    public static function cell(array $rows, string $categoryLabel, string $ga2Label, string $indicatorKey): int
    {
        $columnIndex = array_search($indicatorKey, (array) config('request-management-report.indicator_columns'), true) + 2;

        foreach ($rows as $row) {
            if (($row[0] ?? null) === $categoryLabel && ($row[1] ?? null) === $ga2Label) {
                return (int) $row[$columnIndex];
            }
        }

        throw new RuntimeException("No CSV row for [{$categoryLabel}/{$ga2Label}].");
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>
     */
    public static function ga2Labels(array $rows, string $categoryLabel): array
    {
        $labels = [];

        foreach (array_slice($rows, 1) as $row) { // slice off the header row
            if (($row[0] ?? null) === $categoryLabel) {
                $labels[] = $row[1];
            }
        }

        return $labels;
    }
}
