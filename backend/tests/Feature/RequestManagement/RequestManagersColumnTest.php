<?php

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0373 D-7..D-9: the "Gestori account" column of Gestione Richieste and
// Gestione Iscritti, the twin of the Offerte one.

uses(RefreshDatabase::class);

if (! function_exists('managersColumnActor')) {
    /**
     * @param  array<int, string>  $abilities  `{module}.*` suffixes
     * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
     */
    function managersColumnActor(string $module, array $abilities = ['viewAny', 'view', 'viewAll', 'update'], ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'update', 'appendTeamMember'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        $role = Role::create(['name' => 'managers-column-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "{$module}.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        return User::factory()->create()->assignRole($role);
    }
}

if (! function_exists('managersColumnQuote')) {
    /**
     * @param  array<int, int>  $slots  position => user id
     */
    function managersColumnQuote(array $slots = []): Quote
    {
        $statusId = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'validated')->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id;

        $quote = Quote::factory()->create([
            'quote_workflow_status_id' => $statusId,
            'operator_id' => $slots[ManagerPositions::OPERATOR] ?? null,
        ]);

        foreach ($slots as $position => $userId) {
            $quote->managers()->attach($userId, ['position' => $position]);
        }

        return $quote;
    }
}

if (! function_exists('managersColumnFilteredIds')) {
    /**
     * @param  array<int, string|null>  $values
     * @return array<int, int>
     */
    function managersColumnFilteredIds(array $values): array
    {
        return collect(test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 50,
            'filterModel' => ['managers' => ['filterType' => 'set', 'values' => $values]],
        ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
    }
}

dataset('managers column modules', ['request-management', 'enrollee-management']);

it('declares the column like the Offerte one and projects the team by position (AC-009)', function (string $module) {
    Sanctum::actingAs(managersColumnActor($module));
    $second = User::factory()->create();
    $first = User::factory()->create();
    $quote = managersColumnQuote([1 => $first->id, 3 => $second->id]);

    $column = collect($this->getJson("/api/tables/{$module}/columns")->assertOk()->json('data.columns'))->firstWhere('id', 'managers');

    expect($column)->toMatchArray([
        'label' => 'requestManagement.columns.managers',
        'visible' => true,
        'sortable' => false,
        'filterable' => true,
        'filterType' => 'set',
        'editable' => true,
        'editor' => 'multiselect',
    ]);

    $row = collect($this->postJson("/api/tables/{$module}/rows", ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->firstWhere('id', $quote->id);

    expect(array_column($row['managers'], 'id'))->toBe([$first->id, $second->id])
        ->and($row['managers'][0])->toHaveKeys(['id', 'name', 'avatar_url']);
})->with('managers column modules');

it('filters by a manager at any position and by the blank entry (AC-010)', function () {
    Sanctum::actingAs(managersColumnActor('request-management'));
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $annaFirst = managersColumnQuote([1 => $anna->id]);
    $annaThird = managersColumnQuote([3 => $anna->id, 1 => $zeno->id]);
    $zenoOnly = managersColumnQuote([2 => $zeno->id]);
    $noTeam = managersColumnQuote();

    expect(managersColumnFilteredIds(['Anna']))->toBe([$annaFirst->id, $annaThird->id])
        ->and(managersColumnFilteredIds([null]))->toBe([$noTeam->id])
        ->and(managersColumnFilteredIds(['Zeno', null]))->toBe([$annaThird->id, $zenoOnly->id, $noTeam->id]);

    $this->postJson('/api/tables/request-management/values', ['columnId' => 'managers'])
        ->assertOk()->assertJsonPath('data.values', [null, 'Anna', 'Zeno']);
    $this->postJson('/api/tables/request-management/values', ['columnId' => 'managers', 'search' => 'an'])
        ->assertOk()->assertJsonPath('data.values', ['Anna']);
});

it('edits the whole team from the cell, the Operatore following slot 2 (AC-011)', function (string $module) {
    Sanctum::actingAs(managersColumnActor($module));
    $stays = User::factory()->create();
    $leaves = User::factory()->create();
    $newcomer = User::factory()->create();
    $quote = managersColumnQuote([1 => $stays->id, 2 => $leaves->id]);

    $row = $this->patchJson("/api/tables/{$module}/rows/{$quote->id}", [
        'column' => 'managers',
        'value' => [$stays->id, $newcomer->id],
    ])->assertOk()->json('data');

    expect(array_column($row['managers'], 'id'))->toBe([$stays->id, $newcomer->id])
        ->and($quote->fresh()->operator_id)->toBe($newcomer->id)
        ->and($quote->managers()->pluck('position', 'users.id')->all())->toBe([$stays->id => 1, $newcomer->id => 2]);
})->with('managers column modules');

it('the cell empties the team', function () {
    Sanctum::actingAs(managersColumnActor('request-management'));
    $quote = managersColumnQuote([2 => User::factory()->create()->id]);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", ['column' => 'managers', 'value' => []])->assertOk();

    expect($quote->managers()->count())->toBe(0)
        ->and($quote->fresh()->operator_id)->toBeNull();
});

it('rejects an unknown person (AC-012)', function () {
    Sanctum::actingAs(managersColumnActor('request-management'));
    $quote = managersColumnQuote();

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", ['column' => 'managers', 'value' => [999999]])
        ->assertStatus(422);

    expect($quote->managers()->count())->toBe(0);
});

it('needs a writable manager_slots: readonly and append-only actors stay out of the cell (AC-012)', function (array $abilities) {
    Sanctum::actingAs(managersColumnActor('request-management', $abilities, [
        'resource' => 'request-management',
        'field' => 'manager_slots',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]));
    $operator = User::factory()->create();
    $quote = managersColumnQuote([2 => $operator->id]);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'managers',
        'value' => [$operator->id, User::factory()->create()->id],
    ])->assertForbidden();

    expect($quote->managers()->count())->toBe(1);
})->with([
    'readonly' => [['viewAny', 'view', 'viewAll', 'update']],
    'append only' => [['viewAny', 'view', 'viewAll', 'update', 'appendTeamMember']],
]);

it('the Operatore cell still writes slot 2 alone (AC-013)', function () {
    Sanctum::actingAs(managersColumnActor('request-management'));
    $tutor = User::factory()->create();
    $operator = User::factory()->create();
    $replacement = User::factory()->create();
    $quote = managersColumnQuote([1 => $tutor->id, 2 => $operator->id]);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $replacement->id,
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($replacement->id)
        ->and($quote->managers()->pluck('position', 'users.id')->all())->toBe([$tutor->id => 1, $replacement->id => 2]);
});
