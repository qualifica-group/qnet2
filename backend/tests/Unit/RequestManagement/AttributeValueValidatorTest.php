<?php

declare(strict_types=1);

use App\Models\Company;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Spec 0049, D-4/AC-040/041/042 (spec 0084: reused verbatim by the Quote
// context, constraints: vietato clonarli): AttributeValueValidator validates
// a submitted `attribute_values` map {code => value} against an
// already-resolved applicable-attribute set — agnostic of which domain
// resolved it.

uses(TestCase::class, RefreshDatabase::class);

function makeApplicableAttribute(array $overrides = []): ApplicableAttribute
{
    return new ApplicableAttribute(
        id: $overrides['id'] ?? 1,
        code: $overrides['code'] ?? 'field',
        name: $overrides['name'] ?? 'Field',
        type: $overrides['type'] ?? 'text',
        description: null,
        helpText: null,
        placeholder: null,
        icon: null,
        config: $overrides['config'] ?? [],
        relationTarget: $overrides['relation_target'] ?? null,
        isRequired: $overrides['is_required'] ?? false,
        sortOrder: $overrides['sort_order'] ?? 0,
        options: $overrides['options'] ?? [],
    );
}

/**
 * Runs $callback, expecting it to throw a ValidationException, and returns
 * the exception's error bag — so a test can assert on the exact key
 * (`attribute_values.<code>`), not merely that "some" exception was thrown.
 */
function catchValidationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('Expected a ValidationException to be thrown.');
}

// AC-041: a code outside the applicable set is rejected.
it('rejects a code not in the applicable set', function (): void {
    $attributes = collect([makeApplicableAttribute(['code' => 'known_field'])]);

    $errors = catchValidationErrors(
        fn () => app(AttributeValueValidator::class)->validate($attributes, ['unknown_field' => 'x']),
    );

    expect($errors)->toHaveKey('attribute_values.unknown_field');
});

// AC-041: value not valid for the attribute's type.
it('rejects a value that does not match the attribute type', function (): void {
    $attributes = collect([makeApplicableAttribute(['code' => 'quantity', 'type' => 'integer'])]);

    $errors = catchValidationErrors(
        fn () => app(AttributeValueValidator::class)->validate($attributes, ['quantity' => 'not-a-number']),
    );

    expect($errors)->toHaveKey('attribute_values.quantity');
});

// AC-041: enum value outside the option set.
it('rejects an enum value outside its options', function (): void {
    $attributes = collect([makeApplicableAttribute([
        'code' => 'status',
        'type' => 'enum',
        'options' => [['value' => 'open', 'label' => 'Open', 'color' => null]],
    ])]);

    $errors = catchValidationErrors(
        fn () => app(AttributeValueValidator::class)->validate($attributes, ['status' => 'closed']),
    );

    expect($errors)->toHaveKey('attribute_values.status');
});

// AC-042: required attribute submitted empty/null fails.
it('rejects a required attribute submitted empty or null', function (): void {
    $attributes = collect([makeApplicableAttribute(['code' => 'mandatory_field', 'is_required' => true])]);

    $nullErrors = catchValidationErrors(
        fn () => app(AttributeValueValidator::class)->validate($attributes, ['mandatory_field' => null]),
    );
    $emptyErrors = catchValidationErrors(
        fn () => app(AttributeValueValidator::class)->validate($attributes, ['mandatory_field' => '']),
    );

    expect($nullErrors)->toHaveKey('attribute_values.mandatory_field')
        ->and($emptyErrors)->toHaveKey('attribute_values.mandatory_field');
});

// AC-042: a non-required attribute simply absent from the payload is fine.
it('does not fail a non-required attribute that is absent from the payload', function (): void {
    $attributes = collect([
        makeApplicableAttribute(['code' => 'mandatory_field', 'is_required' => true]),
        makeApplicableAttribute(['code' => 'optional_field', 'is_required' => false]),
    ]);

    $result = app(AttributeValueValidator::class)->validate($attributes, ['mandatory_field' => 'value']);

    expect($result)->toBe(['mandatory_field' => 'value']);
});

// AC-040: relation value must reference an existing row on the target table.
it('rejects a relation value pointing at a non-existent row', function (): void {
    $attributes = collect([makeApplicableAttribute([
        'code' => 'linked_company',
        'type' => 'relation',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
    ])]);

    $errors = catchValidationErrors(
        fn () => app(AttributeValueValidator::class)->validate($attributes, ['linked_company' => 999999]),
    );

    expect($errors)->toHaveKey('attribute_values.linked_company');
});

// AC-040: happy path — one of each representative type passes, and the
// normalizer produces the expected persisted shape.
it('accepts and normalizes a valid attribute_values payload', function (): void {
    $company = Company::factory()->create();

    $attributes = collect([
        makeApplicableAttribute(['code' => 'label', 'type' => 'text', 'is_required' => true]),
        makeApplicableAttribute(['code' => 'quantity', 'type' => 'integer']),
        makeApplicableAttribute(['code' => 'is_active', 'type' => 'boolean']),
        makeApplicableAttribute([
            'code' => 'status',
            'type' => 'enum',
            'options' => [['value' => 'open', 'label' => 'Open', 'color' => null]],
        ]),
        makeApplicableAttribute([
            'code' => 'linked_company',
            'type' => 'relation',
            'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
        ]),
    ]);

    $payload = [
        'label' => '  Warehouse A  ',
        'quantity' => '42',
        'is_active' => '1',
        'status' => 'open',
        'linked_company' => (string) $company->id,
    ];

    $validated = app(AttributeValueValidator::class)->validate($attributes, $payload);
    $normalized = app(AttributeValueNormalizer::class)->normalize($attributes, $validated);

    expect($normalized)->toBe([
        'label' => 'Warehouse A',
        'quantity' => 42,
        'is_active' => true,
        'status' => 'open',
        'linked_company' => $company->id,
    ]);
});
