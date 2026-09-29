<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Support\ManagerPositions;
use Database\Seeders\QualificaSampleRequestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

// `qualifica:seed-sample --bulk` (user directive 2026-09-29: a million
// requests to test on): requests written by multi-row INSERTs, copied from
// templates the real write path created and rolled back.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->create(['category_id' => $category->getKey(), 'name' => 'Bulk product']);
    OperationalSite::factory()->count(2)->create();
    User::factory()->count(4)->create();
});

it('seeds --requests requests, each Offerta on an Opportunity of its own Anagrafica', function (): void {
    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 7])->assertSuccessful();

    expect(Quote::query()->count())->toBe(7)
        ->and(Opportunity::query()->count())->toBe(7)
        ->and(Registry::query()->count())->toBe(7)
        ->and(Opportunity::query()->distinct()->count('registry_id'))->toBe(7)
        ->and(DB::table('personal_data')->where('personable_type', 'registry')->count())->toBe(7)
        ->and(DB::table('contacts')->where('contactable_type', 'personal_data')->where('is_primary', true)->count())->toBe(14);

    Registry::query()->with('personalData')->get()->each(function (Registry $registry): void {
        expect($registry->name)->toBe($registry->personalData->full_name);
    });
});

it('leaves nothing of the templates behind: no lead, no campaign, no activity, no notification', function (): void {
    // The fixture's own factories log their creation: only the delta counts.
    $activities = DB::table('activity_log')->count();

    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 3])->assertSuccessful();

    expect(Lead::query()->count())->toBe(0)
        ->and(Campaign::query()->count())->toBe(0)
        ->and(DB::table('activity_log')->count())->toBe($activities)
        ->and(DB::table('notifications')->count())->toBe(0);
});

it('copies what the write path produced: status, layout, offer row and team', function (): void {
    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 3])->assertSuccessful();

    $real = requestManagementBulkRealQuote();

    Quote::query()->where('id', '!=', $real->id)->with(['lines', 'managers', 'opportunity.managers', 'opportunity.productLines'])->get()
        ->each(function (Quote $quote) use ($real): void {
            expect($quote->quote_workflow_status_id)->toBe($real->quote_workflow_status_id)
                ->and($quote->layout_id)->toBe($real->layout_id)
                ->and($quote->lines)->toHaveCount($real->lines->count())
                ->and($quote->lines->first()->product_id)->toBe($real->lines->first()->product_id)
                ->and((float) $quote->revenue_net)->toBe((float) $quote->lines->sum('net_amount'))
                ->and($quote->opportunity->productLines->pluck('product_category_id')->all())
                ->toBe($real->opportunity->productLines->pluck('product_category_id')->all())
                // The team: three distinct slots, the operator denormalized
                // from its own, promoted onto the opportunity.
                ->and($quote->managers)->toHaveCount(3)
                ->and($quote->managers->firstWhere('pivot.position', ManagerPositions::OPERATOR)->id)->toBe($quote->operator_id)
                ->and($quote->opportunity->managers->pluck('id')->sort()->values()->all())
                ->toBe($quote->managers->pluck('id')->sort()->values()->all());
        });
});

it('codes the Offerte after the numerically highest code and titles them from it', function (): void {
    Quote::factory()->create(['code' => 'QUO-9999']);

    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 2])->assertSuccessful();

    $seeded = Quote::query()->where('code', '!=', 'QUO-9999')->orderBy('id')->with('opportunity')->get();

    expect($seeded->pluck('code')->all())->toBe(['QUO-10000', 'QUO-10001'])
        ->and($seeded->first()->title)->toBe('QUO-10000 - Bulk product')
        ->and($seeded->first()->opportunity->name)->toBe('OPP_'.$seeded->first()->opportunity_id.' - Bulk product');
});

it('accumulates: a second run appends another batch', function (): void {
    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 2])->assertSuccessful();
    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 3])->assertSuccessful();

    expect(Quote::query()->count())->toBe(5)
        ->and(Quote::query()->distinct()->count('code'))->toBe(5);
});

it('refuses a size flag of the chain next to --bulk, seeding nothing', function (string $flag): void {
    test()->artisan('qualifica:seed-sample', ['--bulk' => true, $flag => 2])
        ->assertExitCode(ConsoleCommand::INVALID);

    expect(Quote::query()->count())->toBe(0);
})->with(['--size', '--leads', '--opportunities', '--quotes', '--contracts', '--tasks']);

it('skips with a warning when no request can be created to copy', function (): void {
    Product::query()->delete();

    test()->artisan('qualifica:seed-sample', ['--bulk' => true, '--requests' => 5])->assertSuccessful();

    expect(Quote::query()->count())->toBe(0)
        ->and(Registry::query()->count())->toBe(0);
});

/**
 * A request created through the real module path, to compare the copies with.
 */
function requestManagementBulkRealQuote(): Quote
{
    Registry::factory()->create();
    app(QualificaSampleRequestSeeder::class)->setContainer(app())->__invoke(['requests' => 1]);

    return Quote::query()->latest('id')->with(['lines', 'opportunity.productLines'])->firstOrFail();
}
