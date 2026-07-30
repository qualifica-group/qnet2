<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\CompanySite;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;

/**
 * Server-side guard for the Societa' -> Societa' Sede dependency on a Quote
 * (user directive 2026-07-30): the picked `company_site_id` must belong to
 * the quote's `company_id`.
 *
 * The cascade in the form (the site picker scoped to the chosen company) is
 * an affordance only — security.md §1 "trust nothing": a payload pairing a
 * site with someone else's company must be rejected here, not merely
 * discouraged in the UI. Shared by Store/UpdateQuoteRequest.
 */
trait ValidatesQuoteCompanySite
{
    /**
     * Reject a `company_site_id` whose owning company is not the quote's
     * effective `company_id`. Both keys are resolved the PATCH way: the
     * submitted value when present, otherwise the persisted one ($quote is
     * null on create). A site submitted with no company at all — neither
     * submitted nor persisted — is rejected too: it would be an orphan pair.
     */
    protected function enforceCompanySiteBelongsToCompany(Validator $validator, ?Quote $quote): void
    {
        $siteId = $this->effectiveCompanyKey('company_site_id', $quote);

        if ($siteId === null) {
            return;
        }

        $companyId = $this->effectiveCompanyKey('company_id', $quote);
        $siteCompanyId = CompanySite::query()->whereKey($siteId)->value('company_id');

        // A non-existent site is already a `exists` failure on its own rule;
        // do not stack a second, misleading message on top of it.
        if ($siteCompanyId === null) {
            return;
        }

        if ($companyId === null || (int) $siteCompanyId !== $companyId) {
            $validator->errors()->add('company_site_id', __('quotes.company_site_mismatch'));
        }
    }

    /**
     * The value a nullable FK will actually END UP with after this write:
     * the submitted one when the key is present (even null), else the
     * persisted one.
     */
    private function effectiveCompanyKey(string $key, ?Quote $quote): ?int
    {
        if ($this->has($key)) {
            $value = $this->input($key);

            return $value === null || $value === '' ? null : (int) $value;
        }

        return $quote?->getAttribute($key);
    }
}
