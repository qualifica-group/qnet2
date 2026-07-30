<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * The consumer modules a `App\Models\DocumentLayout` can serve (spec 0069).
 * Single source of truth: FormRequest validation (Rule::enum), the model's
 * `module` cast, the variable catalogue (App\Services\DocumentLayouts\DocumentLayoutVariableCatalog)
 * and the config validator all key off these cases — nothing hardcodes the
 * string elsewhere. Only `Quotes` exists today (spec 0069 scope); the enum is
 * deliberately extensible (new module = new case, no structural change) but
 * no other value is added until a consumer needs it (D-2/scope).
 */
enum DocumentLayoutModule: string
{
    use HasMeta;

    // The label is the translation KEY, not an English source string as in the
    // other enums: `__('Quotes')` would resolve to the `lang/{it,en}/quotes.php`
    // GROUP and return an array, breaking `HasMeta::label(): string`. Keep this
    // key identical to `labelKey()` below.
    #[Label('document_layouts.modules.quotes')]
    #[IsDefault(true)]
    case Quotes = 'quotes';

    /**
     * The i18n key for the module's display label, resolved with __() by the
     * consumer (e.g. `DocumentLayoutResource::module_label`, spec 0070). Kept
     * here — not resolved with __() directly — so a caller that only needs
     * the raw key (e.g. a frontend contract test) does not require the
     * translation catalogue to be loaded.
     */
    public function labelKey(): string
    {
        return "document_layouts.modules.{$this->value}";
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
