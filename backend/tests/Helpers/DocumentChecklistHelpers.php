<?php

declare(strict_types=1);

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('documentChecklistUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentChecklistUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity', 'manageConnection'] as $ability) {
            Permission::findOrCreate("document-checklists.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-checklists.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('documentChecklistItemPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function documentChecklistItemPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => null,
            'name' => 'Documento di identita',
            'description' => null,
            // REQUIREMENT CHANGED (spec 0427): the free text `section` became `section_index` + `date_field`.
            'section_index' => null,
            'is_required' => true,
            'allowed_file_types' => ['pdf'],
            'max_file_size_kb' => 10240,
            'min_files' => 1,
            'max_files' => 1,
            'validation_prompt' => null,
            'max_age_days' => null,
            'date_field' => null,
        ], $overrides);
    }
}

if (! function_exists('documentChecklistPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function documentChecklistPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Apertura pratica',
            'code' => 'apertura',
            'description' => null,
            'is_active' => true,
            'sections' => [],
            'items' => [
                documentChecklistItemPayload(['name' => 'Documento A']),
                documentChecklistItemPayload(['name' => 'Documento B']),
            ],
        ], $overrides);
    }
}

if (! function_exists('documentChecklistWithItems')) {
    /**
     * @param  array<int, string>  $names  one item per name, in this order
     * @param  array<string, mixed>  $attributes
     */
    function documentChecklistWithItems(array $names, array $attributes = []): DocumentChecklist
    {
        $checklist = DocumentChecklist::factory()->create($attributes);

        foreach ($names as $position => $name) {
            DocumentChecklistItem::factory()->create([
                'document_checklist_id' => $checklist->id,
                'position' => $position,
                'name' => $name,
            ]);
        }

        return $checklist;
    }
}
