<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Models\Address;
use App\Models\Attribute;
use App\Models\City;
use App\Models\Contact;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Database\Seeders\Support\RequestSheetLookups;
use Database\Seeders\Support\RequestSheetWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

/**
 * The writer behind the request sheet seeders (user directive 2026-11-07):
 * one catalogue row -> one Gestione Richieste record. Synthetic data only.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Single mode, like Orientamento specialistico: a client may carry another open
    // opportunity (since 2026-11-11 through the flag the migration set on single-mode categories).
    $this->singleCategory = static function (): ProductCategory {
        $category = requestImportScenario()['category'];
        $category->update(['management_mode' => CategoryManagementMode::Single, 'multiple_open_opportunities' => true]);

        return $category;
    };
    $scenario = requestImportScenario();
    $scenario['category']->update(['management_mode' => CategoryManagementMode::Single, 'multiple_open_opportunities' => true]);
    $this->category = $scenario['category'];
    $this->statuses = $scenario['statuses'];
    $this->productId = Product::factory()->create(['category_id' => $this->category->id, 'name' => 'Prodotto Test'])->id;
    $this->actor = User::factory()->create();
    $this->operator = User::factory()->create(['name' => 'Operatore Test']);
    $this->source = Source::factory()->create(['name' => 'Fonte Test']);

    $attribute = Attribute::factory()->create(['code' => 'cpi_name', 'type' => 'text']);
    $this->category->attributes()->attach($attribute->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);

    $this->row = static fn (array $overrides = []): array => [
        'operator' => 'Operatore Test',
        'status' => 'In trattativa',
        'source' => 'Fonte Test',
        'first' => 'Mario',
        'last' => 'Rossi',
        'phones' => ['3330000001', '3330000002'],
        'product' => 'Prodotto Test',
        ...$overrides,
    ];
    $this->write = fn (array $row): string => app(RequestSheetWriter::class)
        ->write($this->actor, $this->category, $row, ['cpi_name' => 'cpi']);
});

it('creates the client, the opportunity and the offer from a row', function () {
    $reporter = Referent::factory()->create(['name' => 'Segnalatore Test']);
    $commercial = Referent::factory()->create(['name' => 'Commerciale Test']);

    $outcome = ($this->write)(($this->row)([
        'email' => 'mario.rossi@example.com',
        'callback' => '2026-11-30',
        'cpi' => 'Centro Test',
        'reporter' => 'Segnalatore Test',
        'commercial' => 'Commerciale Test',
        'notes' => 'Non risponde',
    ]));

    $quote = Quote::query()->with(['opportunity', 'offerLines'])->sole();
    $card = PersonalData::query()->where('personable_type', 'registry')->sole();

    expect($outcome)->toBe(RequestSheetWriter::CREATED)
        ->and([$card->first_name, $card->last_name])->toBe(['Mario', 'Rossi'])
        ->and(Contact::query()->where('contactable_id', $card->id)->pluck('value')->sort()->values()->all())
        ->toBe(['3330000001', '3330000002', 'mario.rossi@example.com'])
        ->and($quote->quote_workflow_status_id)->toBe($this->statuses['negotiation']->id)
        ->and($quote->source_id)->toBe($this->source->id)
        ->and($quote->operator_id)->toBe($this->operator->id)
        ->and($quote->reporter_id)->toBe($reporter->id)
        ->and($quote->commercial_id)->toBe($commercial->id)
        ->and($quote->next_callback_at->format('Y-m-d H:i'))->toBe('2026-11-30 09:00')
        ->and($quote->attribute_values)->toBe(['cpi_name' => 'Centro Test'])
        ->and($quote->offerLines->pluck('product_id')->all())->toBe([$this->productId])
        ->and($quote->opportunity->general_notes)->toBe('Non risponde')
        ->and($quote->is_transferred)->toBeFalse();
});

it('writes nothing on a second run of the same row', function () {
    ($this->write)(($this->row)());

    expect(($this->write)(($this->row)()))->toBe(RequestSheetWriter::ALREADY_PRESENT)
        ->and(Quote::query()->count())->toBe(1)
        ->and(Registry::query()->count())->toBe(1);
});

it('reuses the client with the same phone and name, not a relative on the same phone', function () {
    ($this->write)(($this->row)());
    $category = ($this->singleCategory)();
    $writeOn = fn (array $row): string => app(RequestSheetWriter::class)->write($this->actor, $category, $row, []);

    $writeOn(($this->row)(['status' => 'Attesa _ App. APL', 'product' => null, 'first' => 'MARIO', 'last' => 'ROSSI']));
    $writeOn(($this->row)(['status' => 'Attesa _ App. APL', 'product' => null, 'first' => 'Giulia']));

    expect(Registry::query()->count())->toBe(2)
        ->and(Opportunity::query()->count())->toBe(3);
});

it('matches the client by tax code whatever the name', function () {
    ($this->write)(($this->row)(['tax_code' => 'RSSMRA80A01F839X']));
    $category = ($this->singleCategory)();

    app(RequestSheetWriter::class)->write($this->actor, $category, ($this->row)([
        'status' => 'Attesa _ App. APL', 'product' => null, 'first' => 'M.', 'phones' => ['3339999999'], 'tax_code' => 'rssmra80a01f839x',
    ]), []);

    expect(Registry::query()->count())->toBe(1);
});

it('records a transfer already completed from the origin Sede', function () {
    $origin = OperationalSite::factory()->create();
    Address::factory()->create([
        'addressable_type' => $origin->getMorphClass(),
        'addressable_id' => $origin->id,
        'city_id' => City::factory()->create(['name' => 'Sede Origine'])->id,
    ]);

    ($this->write)(($this->row)(['transferred_from' => 'Sede Origine']));

    $quote = Quote::query()->sole();

    expect($quote->is_transferred)->toBeTrue()
        ->and($quote->transferred_from_operational_site_id)->toBe($origin->id)
        ->and(Activity::query()->where('description', 'Request management contact transfer')->count())->toBe(1);
});

it('leaves the offer without a product row when the row names none', function () {
    ($this->write)(($this->row)(['product' => null]));

    expect(Quote::query()->with('offerLines')->sole()->offerLines)->toHaveCount(0);
});

it('creates a missing company reporter once, as Segnalatore', function () {
    ReferentType::factory()->create(['name' => 'Segnalatore']);
    $lookups = app(RequestSheetLookups::class);

    $lookups->ensureReferents($this->actor, ['Ente Segnalatore' => null, 'Ada Neri' => ['Ada', 'Neri']], 'Segnalatore');
    $lookups->ensureReferents($this->actor, ['Ente Segnalatore' => null], 'Segnalatore');

    expect(Referent::query()->where('name', 'Ente Segnalatore')->count())->toBe(1)
        ->and(Referent::query()->where('name', 'Ada Neri')->exists())->toBeTrue()
        ->and(Referent::query()->where('name', 'Ente Segnalatore')->first()->referentType->name)->toBe('Segnalatore');
});

it('creates a company client with its contact person among its referents', function () {
    ($this->write)(($this->row)([
        'first' => null,
        'last' => null,
        'company' => 'Edile Test Srl',
        'contact_person' => ['first' => 'Anna', 'last' => 'Bianchi', 'title' => 'Dott.ssa'],
    ]));

    $registry = Registry::query()->with(['personalData', 'referents'])->sole();

    expect($registry->personalData->company_name)->toBe('Edile Test Srl')
        ->and($registry->referents->pluck('name')->all())->toBe(['Anna Bianchi'])
        ->and($registry->referents->first()->notes)->toBe('Dott.ssa');
});

it('recognises a client with no phone by email and name on a second run', function () {
    $row = ($this->row)(['phones' => [], 'email' => 'Mario.Rossi@example.com']);
    ($this->write)($row);

    expect(($this->write)($row))->toBe(RequestSheetWriter::ALREADY_PRESENT)
        ->and(Registry::query()->count())->toBe(1);
});

it('adds the row as a new offer on the client open opportunity instead of a second one', function () {
    $first = requestImportScenario()['category'];
    $second = requestImportScenario()['category'];
    $write = fn (ProductCategory $category): string => app(RequestSheetWriter::class)
        ->write($this->actor, $category, ($this->row)(['product' => null, 'notes' => 'Servizio: ISO']), []);

    $write($first);

    expect($write($second))->toBe(RequestSheetWriter::APPENDED)
        ->and(Opportunity::query()->count())->toBe(1)
        ->and(Quote::query()->count())->toBe(2)
        ->and(Opportunity::query()->sole()->productLines()->pluck('product_category_id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and($write($second))->toBe(RequestSheetWriter::ALREADY_PRESENT);
});

it('fails loudly on a name the database does not hold', function () {
    ($this->write)(($this->row)(['operator' => 'Nessuno']));
})->throws(RuntimeException::class, 'user:Nessuno');
