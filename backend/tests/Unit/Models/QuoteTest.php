<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteStatus;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring OpportunityTest.
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the quotes table with the expected columns', function () {
    expect(Schema::hasTable('quotes'))->toBeTrue();
    expect(Schema::hasColumns('quotes', [
        'id', 'code', 'title', 'opportunity_id', 'quote_status_id',
        'commercial_id', 'reporter_id', 'supervisor_id', 'internal_notes',
        'revenue_net', 'revenue_vat', 'cost_net', 'cost_vat', 'margin_net',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

// ---------------------------------------------------------------------------
// #[Fillable] / D-9 / D-13: code and the 5 aggregates are NEVER mass-assignable
// ---------------------------------------------------------------------------

it('mass-assigns title/opportunity_id/quote_status_id/commercial_id/reporter_id/supervisor_id/internal_notes', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteStatus::factory()->create();
    $commercial = Referent::factory()->create();
    $reporter = Referent::factory()->create();
    $supervisor = User::factory()->create();

    $quote = Quote::factory()->make([
        'title' => 'Offerta mass-assignment',
        'opportunity_id' => $opportunity->id,
        'quote_status_id' => $status->id,
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
        'internal_notes' => 'note interna',
    ]);
    $quote->save();

    expect($quote->title)->toBe('Offerta mass-assignment')
        ->and($quote->opportunity_id)->toBe($opportunity->id)
        ->and($quote->quote_status_id)->toBe($status->id)
        ->and($quote->commercial_id)->toBe($commercial->id)
        ->and($quote->reporter_id)->toBe($reporter->id)
        ->and($quote->supervisor_id)->toBe($supervisor->id)
        ->and($quote->internal_notes)->toBe('note interna');
});

it('code is deliberately absent from #[Fillable]: mass-assigning it leaves the NOT NULL column unset (D-13)', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteStatus::factory()->create();

    expect(fn () => Quote::create([
        'title' => 'Senza codice mass-assignato',
        'opportunity_id' => $opportunity->id,
        'quote_status_id' => $status->id,
        'code' => 'HACKED-0001',
    ]))->toThrow(QueryException::class);
});

it('the 5 aggregate columns are deliberately absent from #[Fillable] (D-9): mass-assigning them leaves the DB default 0 in place', function () {
    // Factory::make()/create() run inside Model::unguarded() (Laravel core),
    // which would silently hide a #[Fillable] gap — so this asserts against
    // plain Eloquent mass assignment (Quote::create()), the actual guarded
    // path a FormRequest-driven controller write goes through.
    $opportunity = Opportunity::factory()->create();
    $status = QuoteStatus::factory()->create();

    $quote = new Quote([
        'title' => 'Aggregati non mass-assignabili',
        'opportunity_id' => $opportunity->id,
        'quote_status_id' => $status->id,
        'revenue_net' => 999.99,
        'margin_net' => 500,
    ]);
    $quote->code = 'QUO-9002';
    $quote->save();
    $quote->refresh();

    expect($quote->revenue_net)->toBe('0.00')
        ->and($quote->revenue_vat)->toBe('0.00')
        ->and($quote->cost_net)->toBe('0.00')
        ->and($quote->cost_vat)->toBe('0.00')
        ->and($quote->margin_net)->toBe('0.00');
});

it('the factory assigns code by direct property assignment, bypassing the mass-assignment guard', function () {
    $quote = Quote::factory()->create();

    expect($quote->code)->toStartWith('QUO-');
});

// ---------------------------------------------------------------------------
// casts
// ---------------------------------------------------------------------------

it('casts the 5 aggregate columns to decimal:2', function () {
    $quote = Quote::factory()->create();
    $quote->forceFill([
        'revenue_net' => 30,
        'revenue_vat' => 6.6,
        'cost_net' => 10,
        'cost_vat' => 2.2,
        'margin_net' => 20,
    ])->save();
    $quote->refresh();

    expect($quote->revenue_net)->toBe('30.00')
        ->and($quote->revenue_vat)->toBe('6.60')
        ->and($quote->cost_net)->toBe('10.00')
        ->and($quote->cost_vat)->toBe('2.20')
        ->and($quote->margin_net)->toBe('20.00');
});

// ---------------------------------------------------------------------------
// relations
// ---------------------------------------------------------------------------

it('opportunity()/quoteStatus() are BelongsTo the expected model', function () {
    $quote = new Quote;

    expect($quote->opportunity())->toBeInstanceOf(BelongsTo::class)
        ->and($quote->opportunity()->getRelated())->toBeInstanceOf(Opportunity::class)
        ->and($quote->quoteStatus())->toBeInstanceOf(BelongsTo::class)
        ->and($quote->quoteStatus()->getRelated())->toBeInstanceOf(QuoteStatus::class);
});

it('commercial()/reporter() are BelongsTo Referent via their own FK', function () {
    $quote = new Quote;

    expect($quote->commercial()->getForeignKeyName())->toBe('commercial_id')
        ->and($quote->commercial()->getRelated())->toBeInstanceOf(Referent::class)
        ->and($quote->reporter()->getForeignKeyName())->toBe('reporter_id')
        ->and($quote->reporter()->getRelated())->toBeInstanceOf(Referent::class);
});

it('supervisor() is a BelongsTo User via supervisor_id', function () {
    $relation = (new Quote)->supervisor();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(User::class)
        ->and($relation->getForeignKeyName())->toBe('supervisor_id');
});

it('lines()/offerLines()/costLines() are HasMany QuoteLine, ordered by sort_order (AC-038)', function () {
    $quote = Quote::factory()->create();

    $offerSecond = QuoteLine::factory()->for($quote)->create(['sort_order' => 10]);
    $offerFirst = QuoteLine::factory()->for($quote)->create(['sort_order' => 0]);
    $costLine = QuoteLine::factory()->cost()->for($quote)->create(['sort_order' => 5]);

    expect($quote->lines())->toBeInstanceOf(HasMany::class)
        ->and($quote->lines()->getRelated())->toBeInstanceOf(QuoteLine::class);

    $orderedLineIds = $quote->lines()->pluck('id')->all();
    expect($orderedLineIds)->toBe([$offerFirst->id, $costLine->id, $offerSecond->id]);

    $orderedOfferIds = $quote->offerLines()->pluck('id')->all();
    expect($orderedOfferIds)->toBe([$offerFirst->id, $offerSecond->id]);

    $orderedCostIds = $quote->costLines()->pluck('id')->all();
    expect($orderedCostIds)->toBe([$costLine->id]);
});

it('offerLines()/costLines() are eager-loadable', function () {
    $quote = Quote::factory()->create();
    QuoteLine::factory()->for($quote)->create();
    QuoteLine::factory()->cost()->for($quote)->create();

    $loaded = Quote::query()->with(['offerLines', 'costLines'])->findOrFail($quote->id);

    expect($loaded->relationLoaded('offerLines'))->toBeTrue()
        ->and($loaded->relationLoaded('costLines'))->toBeTrue()
        ->and($loaded->offerLines)->toHaveCount(1)
        ->and($loaded->costLines)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// schema-level constraints / activity log
// ---------------------------------------------------------------------------

it('an opportunity with a quote restricts deletion at the schema level (AC-027)', function () {
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    expect(fn () => DB::table('opportunities')->where('id', $opportunity->id)->delete())
        ->toThrow(QueryException::class);
});

it('logs model activity on the quotes log channel', function () {
    expect(class_uses(Quote::class))->toHaveKey(LogsModelActivity::class);
});
