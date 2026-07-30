<?php

declare(strict_types=1);

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/RenderingTestSupport.php';

// spec 0070 — VariableResolver: token substitution, relation refs, totals,
// dynamic categories, unresolved references, D-6 PII masking (AC-233..238).

// ---------------------------------------------------------------------------
// AC-233 — {quote.*} tokens inside a single run, no residual braces
// ---------------------------------------------------------------------------

it('resolves {quote.code} and {quote.created_at} embedded in one run, leaving no {..} residue (AC-233)', function () {
    $quote = dlrFullQuote();
    Carbon::setTestNow(Carbon::create(2026, 7, 30));
    $quote->forceFill(['created_at' => Carbon::create(2026, 3, 5)])->saveQuietly();

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Offerta {quote.code} del {quote.created_at}'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote->fresh(['opportunity', 'quoteStatus'])));
    $text = dlrDocumentText($zip);

    expect($text)->toContain("Offerta {$quote->code} del 05/03/2026")
        ->and($text)->not->toContain('{')
        ->and($text)->not->toContain('}');
});

// ---------------------------------------------------------------------------
// AC-234 — relation variables, null ref -> empty string
// ---------------------------------------------------------------------------

it('resolves client/commercial/reporter/supervisor/company/operational_site variables (AC-234)', function () {
    $quote = dlrFullQuote();

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Client:{client.name}|Commercial:{commercial.name}|Reporter:{reporter.name}|Supervisor:{supervisor.name}|Company:{company.denomination}|Site:{operational_site.label}'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote));
    $text = dlrDocumentText($zip);

    expect($text)->toContain('Client:Cliente Rossi S.r.l.')
        ->and($text)->toContain('Commercial:Marco Commerciale')
        ->and($text)->toContain('Reporter:Anna Segnalatrice')
        ->and($text)->toContain('Supervisor:Luca Supervisore')
        ->and($text)->toContain('Company:Azienda Emittente S.r.l.');
});

it('renders an empty string for a null relation reference (no company on the quote) (AC-234)', function () {
    $quote = dlrFullQuote(['company_id' => null]);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Company:[{company.denomination}]'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote));

    expect(dlrDocumentText($zip))->toContain('Company:[]');
});

// ---------------------------------------------------------------------------
// AC-235 — totals, *_gross derived (net + vat), 2-decimal comma formatting
// ---------------------------------------------------------------------------

it('resolves {totals.revenue_net}/{totals.revenue_vat}/{totals.revenue_gross} with comma-decimal formatting, gross derived (AC-235)', function () {
    $quote = dlrFullQuote(['revenue_net' => 1250.5, 'revenue_vat' => 275.00]);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Net:{totals.revenue_net}|Vat:{totals.revenue_vat}|Gross:{totals.revenue_gross}'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote));
    $text = dlrDocumentText($zip);

    expect($text)->toContain('Net:1.250,50')
        ->and($text)->toContain('Vat:275,00')
        ->and($text)->toContain('Gross:1.525,50');
});

// ---------------------------------------------------------------------------
// AC-236 — dynamic categories: custom_fields, opportunity_attributes
// ---------------------------------------------------------------------------

it('resolves a valorized {custom_fields.KEY} and {opportunity_attributes.CODE} (AC-236)', function () {
    $quote = dlrFullQuote();
    CustomFieldDefinition::factory()->forEntity('quotes')->create(['key' => 'delivery_notes', 'label' => 'Delivery notes', 'type' => 'text']);
    CustomFieldValue::factory()->create(['entity_type' => 'quotes', 'entity_id' => $quote->id, 'values' => ['delivery_notes' => 'Consegna urgente']]);

    $quote->opportunity->attribute_values = ['floor_size' => '42 mq'];
    $quote->opportunity->save();

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Notes:{custom_fields.delivery_notes}|Attr:{opportunity_attributes.floor_size}'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote->fresh(['opportunity'])));
    $text = dlrDocumentText($zip);

    expect($text)->toContain('Notes:Consegna urgente')
        ->and($text)->toContain('Attr:42 mq');
});

// ---------------------------------------------------------------------------
// AC-237 — unresolved reference -> empty string, generation still succeeds
// ---------------------------------------------------------------------------

it('renders an unknown {quote.does_not_exist} token as an empty string and still generates (AC-237)', function () {
    $quote = dlrFullQuote();

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'X[{quote.does_not_exist}]Y'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote));

    expect(dlrDocumentText($zip))->toContain('X[]Y');
});

it('renders a {custom_fields.KEY} whose definition was deleted as an empty string (AC-237)', function () {
    $quote = dlrFullQuote();

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'Gone[{custom_fields.removed_field}]'])]),
    ]]]);

    $zip = dlrOpenZip(dlrRender($config, $quote));

    expect(dlrDocumentText($zip))->toContain('Gone[]');
});

// ---------------------------------------------------------------------------
// AC-238 — D-6 PII masking on {client.tax_code}
// ---------------------------------------------------------------------------

it('masks {client.tax_code} to an empty string for an actor whose field permissions hide it, but not for another actor (AC-238)', function () {
    $quote = dlrFullQuote();
    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrTextBlock([dlrRun(['text' => 'TaxCode:[{client.tax_code}]'])]),
    ]]]);

    $restrictedActor = dlrActorWithFieldPermissionRole(['resource' => 'registries', 'field' => 'personal_data.tax_code', 'visible' => false, 'editable' => false, 'required' => false]);
    $unrestrictedActor = dlrActorWithFieldPermissionRole();

    $restrictedText = dlrDocumentText(dlrOpenZip(dlrRender($config, $quote, $restrictedActor)));
    $unrestrictedText = dlrDocumentText(dlrOpenZip(dlrRender($config, $quote, $unrestrictedActor)));

    expect($restrictedText)->toContain('TaxCode:[]')
        ->and($unrestrictedText)->toContain('TaxCode:[RSSMRA80A01H501U]')
        ->and($restrictedText)->not->toBe($unrestrictedText);
});
