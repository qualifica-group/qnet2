<?php

use App\Models\Address;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\Concerns\LogsModelActivity;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the company_sites table with the expected columns (no flat contact columns)', function () {
    expect(Schema::hasTable('company_sites'))->toBeTrue();
    expect(Schema::hasColumns('company_sites', [
        'id', 'old_id', 'name', 'notes', 'is_default', 'company_id', 'created_at', 'updated_at',
    ]))->toBeTrue();

    // The former "Altro" columns are gone: those attributes are now universal
    // custom fields (spec 0021, QualificaTemplateSeeder), not flat columns.
    expect(Schema::hasColumn('company_sites', 'accounting_manager_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'company_type'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'surface_sqm'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'other_category_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'color'))->toBeFalse();

    // De-verticalization: the former client-specific ERP settings columns are
    // gone too (dropped by 2026_07_24_100000, re-provisioned as custom fields).
    expect(Schema::hasColumn('company_sites', 'responsible_rda_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'responsible_tickets_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'responsible_validation_contracts_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'responsible_validation_contracts_two_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'proforma_progressive'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'invoice_progressive'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'quotation_layout_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'quotation_header_id'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'quotation_footer_id'))->toBeFalse();

    // The flattened contact columns were removed: contacts/address now live on
    // the personal-data card (HasPersonalData).
    expect(Schema::hasColumn('company_sites', 'email'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'fiscal_code'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'vat_number'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'phone'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'pec'))->toBeFalse();
    expect(Schema::hasColumn('company_sites', 'fax'))->toBeFalse();

    // The site-level "preferred bank" FK moved onto the bank rows (is_primary).
    expect(Schema::hasColumn('company_sites', 'default_bank_id'))->toBeFalse();
});

it('creates the company_site_banks table with the expected columns and cascade FK', function () {
    expect(Schema::hasTable('company_site_banks'))->toBeTrue();
    expect(Schema::hasColumns('company_site_banks', ['id', 'old_id', 'company_site_id', 'name', 'iban', 'notes', 'is_primary', 'created_at', 'updated_at']))->toBeTrue();
});

it('name is required at the database level', function () {
    expect(fn () => DB::table('company_sites')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('old_id is unique among company_sites when present', function () {
    CompanySite::factory()->create(['old_id' => 42]);

    expect(fn () => DB::table('company_sites')->insert([
        'old_id' => 42, 'name' => 'Dup', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('down() reverses both migrations, up() recreates them (in FK order)', function () {
    $banks = require database_path('migrations/2026_07_07_100100_create_company_site_banks_table.php');
    $sites = require database_path('migrations/2026_07_07_100000_create_company_sites_table.php');

    $banks->down();
    expect(Schema::hasTable('company_site_banks'))->toBeFalse();

    $sites->down();
    expect(Schema::hasTable('company_sites'))->toBeFalse();

    $sites->up();
    $banks->up();
    expect(Schema::hasTable('company_sites'))->toBeTrue()
        ->and(Schema::hasTable('company_site_banks'))->toBeTrue();
});

it('the drop-ERP-columns migration backfills non-null values into custom_field_values, merging with any existing row, then drops the columns', function () {
    $migration = require database_path('migrations/2026_07_24_100000_drop_erp_columns_from_company_sites.php');

    $site = CompanySite::factory()->create();
    $rda = User::factory()->create();

    // An existing custom_field_values row (e.g. the former "Altro" section)
    // must survive the merge untouched.
    DB::table('custom_field_values')->insert([
        'entity_type' => 'company-sites',
        'entity_id' => $site->id,
        'values' => json_encode(['color' => 'blue']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Re-create the legacy columns (already dropped by the full migrate run)
    // and populate a subset directly — they are no longer in $fillable.
    $migration->down();
    DB::table('company_sites')->where('id', $site->id)->update([
        'responsible_rda_id' => $rda->id,
        'proforma_progressive' => 42,
        'quotation_layout_id' => 7,
    ]);

    $migration->up();

    expect(Schema::hasColumn('company_sites', 'responsible_rda_id'))->toBeFalse()
        ->and(Schema::hasColumn('company_sites', 'proforma_progressive'))->toBeFalse()
        ->and(Schema::hasColumn('company_sites', 'quotation_layout_id'))->toBeFalse();

    $row = DB::table('custom_field_values')
        ->where('entity_type', 'company-sites')
        ->where('entity_id', $site->id)
        ->first();

    $values = json_decode($row->values, true);

    expect($values['color'])->toBe('blue')
        ->and($values['responsible_rda'])->toBe($rda->id)
        ->and($values['proforma_progressive'])->toBe(42)
        ->and($values['quotation_layout'])->toBe(7)
        ->and($values)->not->toHaveKey('invoice_progressive');
});

// ---------------------------------------------------------------------------
// AC-002 — model relations, morph alias, cascade delete
// ---------------------------------------------------------------------------

it('uses the "company_site" morph alias (enforced morphMap)', function () {
    expect((new CompanySite)->getMorphClass())->toBe('company_site');
});

it('personalData() is a polymorphic morphOne to PersonalData', function () {
    $companySite = CompanySite::factory()->create();
    $card = PersonalData::factory()->company()->for($companySite, 'personable')->create();

    expect($companySite->personalData)->not->toBeNull()
        ->and($companySite->personalData->is($card))->toBeTrue()
        ->and($card->personable_type)->toBe('company_site');
});

it('banks() is a real hasMany to CompanySiteBank', function () {
    $companySite = CompanySite::factory()->create();
    $bank = CompanySiteBank::factory()->for($companySite)->create();

    expect($companySite->banks)->toHaveCount(1)
        ->and($companySite->banks->first()->is($bank))->toBeTrue();
});

it('a bank can be flagged primary (is_primary cast to bool)', function () {
    $companySite = CompanySite::factory()->create();
    $bank = CompanySiteBank::factory()->for($companySite)->create(['is_primary' => true]);

    expect($bank->fresh()->is_primary)->toBeTrue();
});

it('company() is a BelongsTo(Company)', function () {
    $company = Company::factory()->create();

    $companySite = CompanySite::factory()->create(['company_id' => $company->id]);

    expect($companySite->company->is($company))->toBeTrue();
});

it('deleting a site cascades its card (contacts + address), banks and logo', function () {
    Storage::fake('local');
    $companySite = CompanySite::factory()->create();
    $card = PersonalData::factory()->company()->for($companySite, 'personable')->create();
    $address = Address::factory()->primary()->for($card, 'addressable')->create();
    $bank = CompanySiteBank::factory()->for($companySite)->create();
    $companySite->attach(UploadedFile::fake()->image('logo.png'), CompanySite::LOGO_COLLECTION);
    $attachmentId = $companySite->fresh()->logo->id;

    $companySite->delete();

    expect(PersonalData::find($card->id))->toBeNull()
        ->and(Address::find($address->id))->toBeNull()
        ->and(CompanySiteBank::find($bank->id))->toBeNull()
        ->and(Attachment::find($attachmentId))->toBeNull();
});

it('logo()/logoDataUri() mirror the avatar pattern', function () {
    Storage::fake('local');
    $companySite = CompanySite::factory()->create();

    expect($companySite->logoDataUri())->toBeNull();

    $companySite->attach(UploadedFile::fake()->image('logo.png'), CompanySite::LOGO_COLLECTION);
    $companySite->refresh();

    expect($companySite->logo)->not->toBeNull()
        ->and($companySite->logoDataUri())->toStartWith('data:image/');
});

it('logs model activity on the company_sites log channel', function () {
    expect(class_uses(CompanySite::class))->toHaveKey(LogsModelActivity::class);
});

it('factory withPersonalData()/default() states work', function () {
    $companySite = CompanySite::factory()->withPersonalData()->default()->create();

    expect($companySite->personalData)->not->toBeNull()
        ->and($companySite->personalData->type->value)->toBe('company')
        ->and($companySite->is_default)->toBeTrue();
});
