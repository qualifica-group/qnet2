<?php

namespace App\Models;

use App\Enums\ContactTypeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use App\Support\ContactValueNormalizer;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Reusable, polymorphic contact — a single reachable channel (phone, email,
 * website, ...) owned by any entity. Attach with the HasContacts trait
 * (morphMany on `contactable`):
 *
 *     class PersonalData extends BaseModel
 *     {
 *         use HasContacts;
 *     }
 *
 *     $card->contacts;                 // all channels
 *     $card->primaryContact('email');  // the preferred one of a type
 *
 * The "at most one primary per owner+type" invariant is owned by ContactService.
 *
 * @property ContactTypeEnum $type
 * @property string|null $label
 * @property string $value
 * @property string|null $normalized_value
 * @property bool $is_primary
 */
class Contact extends BaseModel
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, LogsModelActivity;

    protected $fillable = [
        'type',
        'label',
        'value',
        'is_primary',
    ];

    protected $casts = [
        'type' => ContactTypeEnum::class,
        'label' => 'string',
        'value' => 'string',
        'normalized_value' => 'string',
        'is_primary' => 'bool',
    ];

    /**
     * `value` and its derived `normalized_value` (spec 0136 D-1) are both
     * personal data. Hiding them keeps them out of the activity log
     * (LogsModelActivity excludes $hidden) and out of default JSON
     * serialization; `value` stays readable via the attribute and through an
     * explicit, authorized resource when one is added.
     *
     * @var list<string>
     */
    protected $hidden = [
        'value',
        'normalized_value',
    ];

    /**
     * Spec 0136 D-1: keep `normalized_value` in sync with `type`/`value` on
     * every save, so `LeadDuplicateMatcher`/`IdentityDuplicateFinder` can match
     * duplicates with an indexed lookup instead of normalizing in PHP. Never
     * fillable — this is the only place it is written.
     */
    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            $contact->normalized_value = self::resolveNormalizedValue($contact);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Read `type`/`value` from the RAW attribute array rather than the cast
     * accessor: a legacy row whose `type` is outside ContactTypeEnum would make
     * the enum cast throw ValueError on access. `tryFrom` mirrors the
     * migration backfill and leaves `normalized_value` null for those rows.
     */
    private static function resolveNormalizedValue(self $contact): ?string
    {
        $type = ContactTypeEnum::tryFrom((string) ($contact->getAttributes()['type'] ?? ''));
        $value = $contact->getAttributes()['value'] ?? null;

        return $type !== null && is_string($value)
            ? ContactValueNormalizer::contact($type, $value)
            : null;
    }
}
