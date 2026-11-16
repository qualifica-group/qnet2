<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\DataObjects\Users\ContactInput;
use App\Models\PersonalData;
use App\Support\ContactValueNormalizer;

/**
 * The legacy contacts not yet on a card (spec 0378 D-6, spec 0398 D-11): a value is
 * already there when the card holds the same type with the same normalized value,
 * manual contacts included. A value becomes primary only when the card has no
 * primary of its type yet.
 */
final class MissingCardContacts
{
    /**
     * @param  array<int, ContactInput>  $inputs
     * @return list<array{type: string, value: string, label: ?string, is_primary: bool}>
     */
    public static function of(PersonalData $card, array $inputs): array
    {
        $known = [];
        $hasPrimary = [];

        foreach ($card->contacts as $contact) {
            $type = (string) $contact->getRawOriginal('type');
            $known[$type.'|'.$contact->getRawOriginal('normalized_value')] = true;
            $hasPrimary[$type] = ($hasPrimary[$type] ?? false) || (bool) $contact->getRawOriginal('is_primary');
        }

        $missing = [];

        foreach ($inputs as $input) {
            $type = $input->data->type->value;
            $key = $type.'|'.ContactValueNormalizer::contact($input->data->type, $input->data->value);

            if (isset($known[$key])) {
                continue;
            }

            $isPrimary = $input->data->isPrimary && ! ($hasPrimary[$type] ?? false);
            $known[$key] = true;
            $hasPrimary[$type] = ($hasPrimary[$type] ?? false) || $isPrimary;
            $missing[] = ['type' => $type, 'value' => $input->data->value, 'label' => $input->data->label, 'is_primary' => $isPrimary];
        }

        return $missing;
    }
}
