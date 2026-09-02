<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\DocxToPdfConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Every node key of the `/api/navigation` `data` payload, at any depth.
 *
 * Deliberately tree-wide instead of scoped to one top-level section: a section
 * whose children are all hidden is dropped entirely by NavigationService, so a
 * section-scoped lookup returns an empty collection for a user without the
 * permission and turns every `not->toContain()` gate assertion vacuous — it
 * would keep passing even if the node leaked. Collecting the whole tree also
 * survives the navigation being reorganised, which is what silently stranded
 * these assertions on the removed `management` section.
 *
 * @param  array<int, array<string, mixed>>  $data
 * @return Collection<int, string>
 */
function navigationNodeKeys(array $data): Collection
{
    return collect($data)->flatMap(fn (array $item): array => [
        $item['key'] ?? null,
        ...navigationNodeKeys($item['children'] ?? [])->all(),
    ])->filter()->values();
}

/**
 * A consistent 4-level geo chain (Country -> State -> Province -> City),
 * shared across every domain that validates BR-4 geo-hierarchy consistency
 * (spec 0027): projects, campaigns, operational sites, addresses. Extracted
 * here (engineering.md §1.2) instead of duplicating the local
 * `siteGeoChain()` pattern already used by OperationalSiteCrudTest.
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function geoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

/**
 * A PDF-shaped placeholder: enough of a header that a caller checking the
 * delivered format sees a PDF, small enough that no test mistakes it for a
 * rendered document.
 */
const FAKE_PDF_BINARY = "%PDF-1.7\n% converted by a test double\n";

/**
 * Swap the LibreOffice conversion (spec 0070: the document is rendered as
 * `.docx` and DELIVERED as `.pdf`) for a double that records what it was
 * given.
 *
 * Two reasons, both about keeping assertions honest. The rendering assertions
 * — variable substitution, header part, which quote the controller resolved —
 * are about the OOXML the generator produced, and PDF is a lossy place to look
 * for them; the returned getter hands that exact `.docx` back. And a real
 * LibreOffice run costs ~2s per call, which would buy nothing in a test that
 * only asserts a 200. The real binary is exercised end-to-end by
 * QuoteDocumentPdfTest.
 *
 * @return Closure(): string the last `.docx` handed to the converter
 */
function captureDocxToPdfConversion(): Closure
{
    $recorder = new class extends DocxToPdfConverter
    {
        public string $docx = '';

        public function convert(string $docx): string
        {
            $this->docx = $docx;

            return FAKE_PDF_BINARY;
        }
    };

    app()->instance(DocxToPdfConverter::class, $recorder);

    return static fn (): string => $recorder->docx;
}

/**
 * Grant the import module ability to $user. The dedicated `import-runs.*` set
 * was removed (2026-07-17): the whole module — reads, writes, delete, export —
 * is now gated by the lead module's single `leads.import` ability, so any
 * non-empty $abilities request maps to granting `leads.import` once. The
 * parameter is kept so existing call sites read unchanged; ownership (for
 * view/delete) is enforced separately by the run's `user_id`.
 *
 * @param  array<int, string>  $abilities
 */
function grantImportRunsPermissions(User $user, array $abilities): void
{
    Permission::findOrCreate('leads.import');

    if ($abilities !== []) {
        $user->givePermissionTo('leads.import');
    }
}

/**
 * The two things spec 0096 (D-1) made mandatory on every work order write:
 * `start_date` and at least one Responsabile (`supervisor_ids`). Spread into
 * a create payload (`...workOrderRequiredFields()`) so the ~30 existing call
 * sites state the new requirement once instead of each inventing its own.
 *
 * @return array{start_date: string, supervisor_ids: array<int, int>}
 */
function workOrderRequiredFields(): array
{
    return [
        'start_date' => '2026-09-10',
        'supervisor_ids' => [User::factory()->create()->id],
    ];
}
