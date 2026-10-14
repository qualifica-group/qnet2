<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Legacy work order fixtures live in tests/Helpers/WorkOrderMigrationHelpers.php.

if (! function_exists('legacyWorkOrderAttribute')) {
    /**
     * A WORK_ORDER-context attribute linked to the category, with its enum options.
     *
     * @param  array<string, mixed>  $extra
     * @param  array<int, string>  $options
     */
    function legacyWorkOrderAttribute(ProductCategory $category, string $code, string $type, array $extra = [], array $options = []): Attribute
    {
        $attribute = Attribute::factory()->create(['code' => $code, 'type' => $type, ...$extra]);

        foreach ($options as $index => $value) {
            $attribute->options()->create(['value' => $value, 'label' => "Option {$value}", 'sort_order' => $index]);
        }

        $category->attributes()->attach($attribute->id, [
            'is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value,
        ]);

        return $attribute;
    }
}

beforeEach(function () {
    seedMigrationsConfig();
    $quote = Quote::factory()->create(['old_id' => 42]);
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'old_id' => 900]);
    $this->category = $line->product->category;
});

it('writes the legacy flexible fields applicable to the work order lines (G-20/G-21)', function () {
    $registry = Registry::factory()->create(['old_id' => 300]);
    legacyWorkOrderAttribute($this->category, 'scopo', 'textarea');
    legacyWorkOrderAttribute($this->category, 'stato_consulenza', 'enum', options: ['1029', '1030']);
    legacyWorkOrderAttribute($this->category, 'ore_lavorazione', 'integer');
    legacyWorkOrderAttribute($this->category, 'status_contabile', 'boolean');
    legacyWorkOrderAttribute($this->category, 'totale_commessa_manuale', 'decimal');
    legacyWorkOrderAttribute($this->category, 'avv_company_id', 'relation', [
        'relation_target' => ['entity_type' => 'registries', 'cardinality' => 'one'],
    ]);
    legacyWorkOrderAttribute($this->category, 'orderiso_verificas', 'table', ['config' => [
        'columns' => [
            ['key' => 'data_verifica', 'label' => 'Data Verifica I.', 'type' => 'date', 'required' => true],
            ['key' => 'ispettore_verifica', 'label' => 'Ispettore', 'type' => 'text', 'required' => true],
        ],
        'selectable' => ['key' => 'attivo', 'label' => 'Attivo'],
        'summary' => ['column' => 'data_verifica', 'strategy' => 'selected'],
    ]]);

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'quote_line_ids' => [900],
        'attribute_values' => [
            'scopo' => 'Erogazione di corsi di formazione',
            'stato_consulenza' => '1029',
            'ore_lavorazione' => 16,
            'status_contabile' => true,
            'totale_commessa_manuale' => 1460.2,
            'avv_company_id' => 300,
            'orderiso_verificas' => ['rows' => [
                ['data_verifica' => '2026-06-30', 'ispettore_verifica' => 'Franciosi', 'attivo' => false],
                ['data_verifica' => '2026-10-09', 'ispettore_verifica' => 'Franciosi', 'attivo' => true],
            ]],
        ],
    ])]);

    $values = WorkOrder::query()->where('old_id', 17)->sole()->attribute_values;

    expect($run->report)->toBeNull()
        ->and($values['scopo'])->toBe('Erogazione di corsi di formazione')
        ->and($values['stato_consulenza'])->toBe('1029')
        ->and($values['ore_lavorazione'])->toBe(16)
        ->and($values['status_contabile'])->toBeTrue()
        ->and((float) $values['totale_commessa_manuale'])->toBe(1460.2)
        ->and($values['avv_company_id'])->toBe($registry->id)
        ->and($values['orderiso_verificas']['rows'])->toHaveCount(2)
        ->and($values['orderiso_verificas']['rows'][1])->toMatchArray([
            'data_verifica' => '2026-10-09', 'ispettore_verifica' => 'Franciosi', 'attivo' => true,
        ]);
});

it('skips non-applicable codes and invalid values with a warning, keeping the work order and the other values', function () {
    legacyWorkOrderAttribute($this->category, 'ente_riferimento', 'text');
    legacyWorkOrderAttribute($this->category, 'stato_certificato', 'enum', options: ['1', '2']);

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'quote_line_ids' => [900],
        'attribute_values' => [
            'ente_riferimento' => 'GC',
            'stato_certificato' => '9',
            'soa_attestante' => 'La Soatech',
            'gol_num_discenti' => 12,
        ],
    ])]);

    $warnings = legacyWorkOrderWarnings($run);

    expect($run->created_rows)->toBe(1)
        ->and(WorkOrder::query()->where('old_id', 17)->sole()->attribute_values)->toBe(['ente_riferimento' => 'GC'])
        ->and($warnings)->toContain('Legacy additional information not applicable to the work order categories, not imported: soa_attestante, gol_num_discenti.')
        ->and(collect($warnings)->contains(fn (string $warning): bool => str_starts_with($warning, 'Legacy additional information stato_certificato not imported:')))->toBeTrue();
});

it('creates the legacy phases in order with their closing, without tasks nor activity log (G-22)', function () {
    $closer = User::factory()->create(['old_id' => 33]);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyWorkOrdersImport([legacyWorkOrderRecord([
        'stages' => [
            ['name' => 'Lavorazione', 'closed_at' => '2022-09-09 00:00:00', 'closed_by_user_id' => 33],
            ['name' => 'Customer', 'closed_at' => '2022-09-10 00:00:00', 'closed_by_user_id' => 99],
            ['name' => 'Validazione', 'closed_at' => null, 'closed_by_user_id' => null],
            ['name' => '  ', 'closed_at' => null, 'closed_by_user_id' => null],
        ],
    ])], $actor);

    $stages = WorkOrder::query()->where('old_id', 17)->sole()->stages()->orderBy('sort_order')->get();

    expect($run->created_rows)->toBe(1)
        ->and($stages->pluck('name')->all())->toBe(['Lavorazione', 'Customer', 'Validazione'])
        ->and($stages->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($stages[0]->closed_at->toDateTimeString())->toBe('2022-09-09 00:00:00')
        ->and($stages[0]->closed_by_id)->toBe($closer->id)
        ->and($stages[1]->closed_at->toDateTimeString())->toBe('2022-09-10 00:00:00')
        ->and($stages[1]->closed_by_id)->toBeNull()
        ->and($stages[2]->closed_at)->toBeNull()
        ->and(legacyWorkOrderWarnings($run))->toContain('Stage Customer: closing user (legacy id 99) not migrated; closed without author.')
        ->and(Task::query()->count())->toBe(0)
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});
