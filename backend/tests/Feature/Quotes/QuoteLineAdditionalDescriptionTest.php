<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Resources\QuoteLineResource;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Quotes\QuoteLineRules;
use App\Services\DocumentLayouts\Rendering\Blocks\ProductLineColumnResolver;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * `quote_lines.additional_description`: persisted from the line payload,
 * preserved when a channel resubmits the row without the key, validated, and
 * exposed both on the API resource and as the products_table column key.
 */
uses(RefreshDatabase::class);

function additionalDescriptionQuote(?string $description): Quote
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity = Opportunity::factory()->create();

    return app(QuoteService::class)->create(new CreateQuoteData(
        code: null,
        title: 'Offerta',
        opportunityId: $opportunity->id,
        workflowStatusId: null,
        note: null,
        commercialId: null,
        commercialIdSubmitted: false,
        reporterId: null,
        reporterIdSubmitted: false,
        supervisorId: null,
        supervisorIdSubmitted: false,
        internalNotes: null,
        offerLines: [QuoteLineData::fromValidated([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10,
            'additional_description' => $description,
        ])],
        costLines: null,
    ), User::factory()->create());
}

/** @param array<string, mixed> $extra */
function resubmitFirstOfferLine(Quote $quote, array $extra): QuoteLine
{
    $line = $quote->offerLines()->sole();
    $updated = app(QuoteService::class)->update($quote, new UpdateQuoteData(offerLines: [QuoteLineData::fromValidated([
        'id' => $line->id,
        'product_id' => $line->product_id,
        'quantity' => 1,
        'unit_price' => 10,
        ...$extra,
    ])]), User::factory()->create());

    return $updated->offerLines()->sole();
}

it('persists the additional description sent on a line', function () {
    $quote = additionalDescriptionQuote('Installazione inclusa');

    expect($quote->offerLines()->sole()->additional_description)->toBe('Installazione inclusa');
});

it('keeps the stored description when a resubmitted row omits the key', function () {
    $line = resubmitFirstOfferLine(additionalDescriptionQuote('Installazione inclusa'), []);

    expect($line->additional_description)->toBe('Installazione inclusa');
});

it('updates and clears the description when the row carries the key', function () {
    $quote = additionalDescriptionQuote('Prima');

    expect(resubmitFirstOfferLine($quote, ['additional_description' => 'Dopo'])->additional_description)->toBe('Dopo')
        ->and(resubmitFirstOfferLine($quote, ['additional_description' => null])->additional_description)->toBeNull();
});

it('rejects a description longer than the maximum length', function () {
    $validator = Validator::make([
        'title' => 'x',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [[
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit_price' => 10,
            'additional_description' => str_repeat('a', QuoteLineRules::ADDITIONAL_DESCRIPTION_MAX_LENGTH + 1),
        ]],
    ], (new StoreQuoteRequest)->rules());

    expect($validator->errors()->has('offer_lines.0.additional_description'))->toBeTrue();
});

it('exposes the description on the resource and as the products_table column key', function () {
    $line = additionalDescriptionQuote('  Consegna in 10 giorni ')->offerLines()->sole()->load('quote');
    $request = Request::create('/');
    $request->setUserResolver(fn () => User::factory()->create());

    expect((new QuoteLineResource($line))->toArray($request)['additional_description'])->toBe('  Consegna in 10 giorni ')
        ->and(app(ProductLineColumnResolver::class)->value('additional_description', $line))->toBe('Consegna in 10 giorni');
});
