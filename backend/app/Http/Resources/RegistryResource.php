<?php

namespace App\Http\Resources;

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Registry
 */
class RegistryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source_id' => $this->source_id,
            'source' => $this->toRef($this->source),
            'sector_ids' => $this->sectors->pluck('id')->all(),
            'sectors' => $this->toRefList($this->sectors),
            'referent_ids' => $this->referents->pluck('id')->all(),
            // Referents as person cards (name + primary contacts).
            'referents' => $this->toPersonRefList($this->referents),
            'manager_ids' => $this->managers->pluck('id')->all(),
            // Managers as ordered "G.A. n" person cards (name + position +
            // primary contacts); `manager_slots` is the gap-aware slot array
            // (index+1 = G.A. n, null = empty slot) the form/detail render from.
            'managers' => $this->toManagerRefList($this->managers),
            'manager_slots' => $this->buildManagerSlots($this->managers),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->toPersonRef($this->supervisor),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->toPersonRef($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->toPersonRef($this->reporter),
            'vat_group' => $this->vat_group,
            'is_supplier' => $this->is_supplier,
            'is_qualified_supplier' => $this->is_qualified_supplier,
            'agreement_status' => $this->agreement_status,
            'agreement_notes' => $this->agreement_notes,
            'general_notes' => $this->general_notes,
            'size_class' => $this->size_class,
            'employee_count' => $this->employee_count,
            // The nested personal-data tree, or null — always present as a
            // key (the Service always eager-loads
            // `personalData.contacts`/`personalData.addresses`).
            'personal_data' => $this->personalData !== null
                ? new PersonalDataResource($this->personalData)
                : null,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * A single to-one relation projected to {id, name}, or null — the
     * Service always eager-loads these, so this never triggers a lazy load.
     *
     * @return array{id: int, name: string}|null
     */
    private function toRef(mixed $related): ?array
    {
        return $related !== null ? ['id' => $related->id, 'name' => $related->name] : null;
    }

    /**
     * A responsible person (supervisor/commercial/reporter — a User or a
     * Referent, both `HasPersonalData`) projected to {id, name, primary_contacts}.
     * The service eager-loads `<rel>.personalData.contacts`, so surfacing each
     * person's PRIMARY contacts (one per type) beside the name never lazy-loads.
     *
     * @return array{id: int, name: string, primary_contacts: array<int, array{type: string, icon: string|null, label: string, value: string}>}|null
     */
    private function toPersonRef(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
            'primary_contacts' => (new PrimaryContactColumn)->format($this->primaryContactsOf($related)),
        ];
    }

    /**
     * The person's primary contacts (is_primary), or null when they have no
     * card. Reads from the eager-loaded relation only.
     *
     * @return Collection<int, Contact>|null
     */
    private function primaryContactsOf(Model $person): ?Collection
    {
        /** @var PersonalData|null $card */
        $card = $person->personalData;

        return $card !== null
            ? $card->contacts->where('is_primary', true)->values()
            : null;
    }

    /**
     * A to-many relation projected to a list of {id, name}.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function toRefList(iterable $related): array
    {
        return collect($related)->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name])->all();
    }

    /**
     * A person list (referents) projected to {id, name, primary_contacts},
     * mirroring toPersonRef for the multi relations.
     *
     * @param  iterable<int, Model>  $related
     * @return array<int, array{id: int, name: string, primary_contacts: array<int, array{type: string, icon: string|null, label: string, value: string}>}>
     */
    private function toPersonRefList(iterable $related): array
    {
        return collect($related)
            ->map(fn (Model $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'primary_contacts' => (new PrimaryContactColumn)->format($this->primaryContactsOf($person)),
            ])
            ->all();
    }

    /**
     * Managers as ordered "G.A. n" person cards: like toPersonRefList plus the
     * pivot `position`. Already ordered by position (relation orderByPivot).
     *
     * @param  iterable<int, Model>  $managers
     * @return array<int, array{id: int, name: string, position: int, primary_contacts: array<int, array{type: string, icon: string|null, label: string, value: string}>}>
     */
    private function toManagerRefList(iterable $managers): array
    {
        return collect($managers)
            ->map(fn (Model $manager): array => [
                'id' => $manager->id,
                'name' => $manager->name,
                'position' => (int) $manager->pivot->position,
                'primary_contacts' => (new PrimaryContactColumn)->format($this->primaryContactsOf($manager)),
            ])
            ->all();
    }

    /**
     * The gap-aware manager slot array: a list of `user_id|null` whose length is
     * the highest occupied position, each manager placed at `position - 1` and
     * every unoccupied index left null (a persistent empty "G.A. n" slot). An
     * empty relation yields an empty array.
     *
     * @param  iterable<int, Model>  $managers
     * @return array<int, int|null>
     */
    private function buildManagerSlots(iterable $managers): array
    {
        $byPosition = [];
        $max = 0;

        foreach ($managers as $manager) {
            $position = (int) $manager->pivot->position;
            $byPosition[$position] = $manager->id;
            $max = max($max, $position);
        }

        $slots = [];
        for ($position = 1; $position <= $max; $position++) {
            $slots[] = $byPosition[$position] ?? null;
        }

        return $slots;
    }
}
