<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\DocumentLayoutModule;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Server-side guard for `quotes.layout_id` (spec 0070, data_contract
 * <validation>): the referenced DocumentLayout must belong to the `quotes`
 * module (`layout_module_mismatch`) and be active (`layout_inactive`) —
 * UNLESS it is exactly the value already persisted on the quote being
 * edited, so a preventivo whose layout was deactivated afterwards stays
 * savable (D-3). Shared by Store/UpdateQuoteRequest.
 *
 * Reads via the plain query builder (`DB::table`), not the Eloquent model:
 * `DocumentLayout::module` is cast to the `DocumentLayoutModule` enum, which
 * throws on an unrecognized value — exactly the "wrong module" row this
 * guard must be able to read without blowing up.
 *
 * `exists:document_layouts,id` on the field's own rule already rejects a
 * nonexistent id; this trait only adds the checks that rule cannot express.
 * The tendina in the form is an affordance only — security.md §1
 * "trust nothing".
 */
trait ValidatesQuoteLayout
{
    protected function enforceQuoteLayout(Validator $validator, ?Quote $quote): void
    {
        if (! $this->has('layout_id') || $this->input('layout_id') === null) {
            return;
        }

        $layoutId = (int) $this->input('layout_id');
        $layout = DB::table('document_layouts')->where('id', $layoutId)->first(['module', 'is_active']);

        // A nonexistent id is already an `exists` failure on its own rule;
        // do not stack a second, misleading message on top of it.
        if ($layout === null) {
            return;
        }

        if ($layout->module !== DocumentLayoutModule::Quotes->value) {
            $validator->errors()->add('layout_id', __('quotes.layout_module_mismatch'));

            return;
        }

        if (! $layout->is_active && $layoutId !== $quote?->layout_id) {
            $validator->errors()->add('layout_id', __('quotes.layout_inactive'));
        }
    }
}
