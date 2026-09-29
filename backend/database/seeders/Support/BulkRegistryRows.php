<?php

namespace Database\Seeders\Support;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Support\ContactValueNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * The Anagrafica of one bulk sample request, as rows for a multi-row INSERT:
 * registry, personal-data card (the source of the denormalized
 * `registries.name`) and its primary email and phone — the stack the Gestione
 * Richieste grid reads. The values come from the models' own factories, the
 * derived ones (`name`, `normalized_value`) from the code that derives them on
 * a save, since an INSERT runs no model event.
 */
final class BulkRegistryRows
{
    /** One in four Anagrafiche is a private individual, as in QualificaSampleLeadSeeder. */
    private const int INDIVIDUAL_EVERY = 4;

    /** @var array<string, Factory<Model>> */
    private readonly array $factories;

    public function __construct()
    {
        $this->factories = [
            'company' => PersonalData::factory()->company(),
            'individual' => PersonalData::factory()->individual(),
            'registry' => Registry::factory(),
            'email' => Contact::factory()->email()->primary(),
            'phone' => Contact::factory()->phone()->primary(),
        ];
    }

    /**
     * Appends to $rows, by table, the Anagrafica numbered $index.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows
     * @param  array{created_at: string, updated_at: string}  $timestamps
     */
    public function add(array &$rows, int $index, int $registryId, int $cardId, ?int $sourceId, array $timestamps): void
    {
        $card = $this->factories[$index % self::INDIVIDUAL_EVERY === 0 ? 'individual' : 'company']->raw();

        $rows['registries'][] = [
            ...$this->factories['registry']->raw(),
            'id' => $registryId,
            'name' => (new PersonalData)->setRawAttributes($card)->full_name,
            'source_id' => $sourceId,
            ...$timestamps,
        ];
        $rows['personal_data'][] = [...$card, 'id' => $cardId, 'personable_type' => 'registry', 'personable_id' => $registryId, ...$timestamps];

        foreach (['email', 'phone'] as $type) {
            $contact = $this->factories[$type]->raw();
            $rows['contacts'][] = [
                ...$contact,
                'contactable_type' => 'personal_data',
                'contactable_id' => $cardId,
                'normalized_value' => ContactValueNormalizer::contact(ContactTypeEnum::from($contact['type']), $contact['value']),
                ...$timestamps,
            ];
        }
    }
}
