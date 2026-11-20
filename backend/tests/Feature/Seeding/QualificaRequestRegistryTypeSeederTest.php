<?php

use App\Models\ProductCategory;
use Database\Seeders\QualificaRequestRegistryTypeSeeder as Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** Spec 0410, user directive 2026-11-18: Formazione and APL natural person, every other branch company. */
uses(RefreshDatabase::class);

it('sets Formazione and APL branches to individual and every other branch to company, idempotently', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $formazioneChild = ProductCategory::factory()->childOf($formazione)->create();
    $apl = ProductCategory::factory()->create(['name' => 'APL', 'request_registry_type' => 'company']);
    $consulenza = ProductCategory::factory()->create(['name' => 'Consulenza']);
    $consulenzaGrandchild = ProductCategory::factory()
        ->childOf(ProductCategory::factory()->childOf($consulenza)->create())
        ->create();
    $legacyFormazione = ProductCategory::factory()->create(['name' => '[OLD] FORMAZIONE', 'is_legacy' => true, 'current_category_id' => $formazione->id]);
    $legacyChild = ProductCategory::factory()->childOf($legacyFormazione)->create(['is_legacy' => true]);
    $legacyUnlinked = ProductCategory::factory()->create(['name' => '[OLD] HR', 'is_legacy' => true]);

    $this->seed(Seeder::class);
    $this->seed(Seeder::class);

    $typeOf = fn (ProductCategory $category): string => $category->fresh()->request_registry_type->value;

    expect($typeOf($formazione))->toBe('individual')
        ->and($typeOf($formazioneChild))->toBe('individual')
        ->and($typeOf($apl))->toBe('individual')
        ->and($typeOf($legacyFormazione))->toBe('individual')
        ->and($typeOf($legacyChild))->toBe('individual')
        ->and($typeOf($consulenza))->toBe('company')
        ->and($typeOf($consulenzaGrandchild))->toBe('company')
        ->and($typeOf($legacyUnlinked))->toBe('company');
});
