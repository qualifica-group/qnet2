<?php

namespace Database\Seeders;

use App\Models\CustomFieldDefinition;
use App\Services\ProformaRequests\ProformaRequestTaskDispatcher;
use Illuminate\Database\Seeder;

/**
 * One-shot (user request 2026-11-23):
 * `php artisan db:seed --class=QualificaResponsibleProformaFieldSeeder --force`.
 *
 * Adds to the sedi the "Responsabile emissione proforma" field (spec 0421):
 * a select over the users, read by ProformaRequestTaskDispatcher to assign
 * the Task a proforma request opens. Only this definition: re-running the
 * whole QualificaTemplateSeeder would also reset every other template field
 * and the default document layout. A fresh install gets it from the template.
 * Appended after the existing sede fields; an existing definition is left as
 * it is, so a re-run changes nothing.
 */
class QualificaResponsibleProformaFieldSeeder extends Seeder
{
    public const string ENTITY_TYPE = 'company-sites';

    public const string LABEL = 'Responsabile emissione proforma';

    public function run(): void
    {
        $definition = CustomFieldDefinition::firstOrCreate(
            ['entity_type' => self::ENTITY_TYPE, 'key' => ProformaRequestTaskDispatcher::RESPONSIBLE_FIELD],
            [
                'type' => 'relation',
                'label' => self::LABEL,
                'sort_order' => $this->nextSortOrder(),
                'is_indexed' => false,
                'is_active' => true,
                'relation_target' => QualificaTemplateSeeder::MANAGER_RELATION_TARGET,
            ],
        );

        $this->command?->info($definition->wasRecentlyCreated
            ? 'Campo "'.self::LABEL.'" creato.'
            : 'Campo "'.self::LABEL.'" gia\' presente, nessuna modifica.');
    }

    private function nextSortOrder(): int
    {
        $max = CustomFieldDefinition::query()->where('entity_type', self::ENTITY_TYPE)->max('sort_order');

        return $max === null ? 0 : (int) $max + 1;
    }
}
