<?php

namespace App\Enums;

use App\Enums\Attributes\Icon;
use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Channel a Contact represents (phone, email, ...). Single source of truth for
 * the allowed values, shared by the model cast, the factory and the per-type
 * validation of the contact `value` (e.g. an email type must hold a valid
 * email, a website a valid URL).
 */
enum ContactTypeEnum: string
{
    use HasMeta;

    #[Label('Phone')]
    #[Icon('phone')]
    #[IsDefault(true)]
    case Phone = 'phone';

    #[Label('Fax')]
    #[Icon('printer')]
    case Fax = 'fax';

    #[Label('Email')]
    #[Icon('mail')]
    case Email = 'email';

    #[Label('Pec')]
    #[Icon('shield-check')]
    case Pec = 'pec';

    #[Label('Website')]
    #[Icon('globe')]
    case Website = 'website';

    /**
     * Resolve a stored/raw value to a type, defaulting to Phone for anything
     * unknown or missing — so the domain never holds an out-of-contract type.
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Phone;
    }

    /**
     * The Laravel validation rules the contact `value` must satisfy for this
     * type. Keyed-by-type validation lives here so it stays next to the enum
     * (single source of truth) and is reused by the FormRequest.
     *
     * @return array<int, string>
     */
    public function valueRules(): array
    {
        return match ($this) {
            self::Email, self::Pec => ['email:rfc'],
            self::Website => ['url'],
            self::Phone, self::Fax => ['regex:/^\+?[0-9 ().-]{6,20}$/'],
        };
    }
}
