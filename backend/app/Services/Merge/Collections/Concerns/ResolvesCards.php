<?php

namespace App\Services\Merge\Collections\Concerns;

use App\Enums\PersonalDataTypeEnum;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contacts and addresses hang off the personal-data card, not off the record
 * (spec 0394, D-6): resolves the cards involved in a merge.
 */
trait ResolvesCards
{
    /**
     * Card id => owning record id, for the records that have a card.
     *
     * @param  Collection<int, Model>  $records
     * @return array<int, int>
     */
    private function cardOwners(Collection $records): array
    {
        $owners = [];

        foreach ($records as $record) {
            $card = $record->getRelationValue('personalData') ?? $record->personalData()->first();

            if ($card !== null) {
                $owners[(int) $card->getKey()] = (int) $record->getKey();
            }
        }

        return $owners;
    }

    /**
     * The survivor's card id, creating an empty card when the survivor never
     * had one (the chosen profile is written over it afterwards).
     *
     * @param  Collection<int, Model>  $merged
     */
    private function survivorCardId(Model $survivor, Collection $merged): int
    {
        /** @var PersonalData|null $card */
        $card = $survivor->personalData()->first();

        if ($card === null) {
            $type = $merged->map(static fn (Model $record) => $record->personalData()->first()?->type)->filter()->first();
            $card = $survivor->personalData()->create(['type' => ($type ?? PersonalDataTypeEnum::Company)->value]);
        }

        return (int) $card->getKey();
    }
}
