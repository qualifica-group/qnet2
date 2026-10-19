<?php

use App\Models\BusinessFunction;
use Database\Seeders\QualificaBusinessFunctionSeeder;
use Database\Seeders\QualificaCatalog\BusinessFunctionCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The business functions of the client's organisation chart (Pianta Organica
// 8.09, user decision 2026-10-19), names without the sheet's code prefix,
// plus "Direzione Generale" (REQUIREMENT CHANGED, user decision 2026-10-19).
uses(RefreshDatabase::class);

it('creates the 25 functions of the organisation chart with their BU/BS type, idempotently', function (): void {
    test()->seed(QualificaBusinessFunctionSeeder::class);
    test()->seed(QualificaBusinessFunctionSeeder::class);

    $functions = BusinessFunction::query()->orderBy('id')->get();

    expect($functions->pluck('name')->all())->toBe(array_keys(BusinessFunctionCatalogue::FUNCTIONS))
        ->and($functions)->toHaveCount(25)
        ->and($functions->every(fn (BusinessFunction $function): bool => $function->is_active))->toBeTrue()
        ->and($functions->firstWhere('name', 'Formazione'))
        ->is_business_unit->toBeTrue()
        ->is_business_service->toBeFalse()
        ->and($functions->firstWhere('name', 'Amministrazione e Contabilità'))
        ->is_business_unit->toBeFalse()
        ->is_business_service->toBeTrue();
});

it('keeps a function already there under the same name in another case, filling only a missing type', function (): void {
    $untyped = BusinessFunction::factory()->create(['name' => 'FORMAZIONE', 'is_business_unit' => false, 'is_business_service' => false]);
    $typedByHand = BusinessFunction::factory()->create(['name' => 'Commerciale', 'is_business_unit' => true, 'is_business_service' => false]);

    test()->seed(QualificaBusinessFunctionSeeder::class);

    expect(BusinessFunction::query()->whereRaw('LOWER(name) = ?', ['formazione'])->count())->toBe(1)
        ->and($untyped->fresh())
        ->name->toBe('FORMAZIONE')
        ->is_business_unit->toBeTrue()
        ->and($typedByHand->fresh())
        ->is_business_unit->toBeTrue()
        ->is_business_service->toBeFalse()
        ->and(BusinessFunction::query()->count())->toBe(25);
});
