<?php

namespace App\Imports\Leads;

use App\Enums\ContactTypeEnum;
use App\Imports\Recognition\CampaignRecognizer;
use App\Services\Leads\LeadSourceResolver;
use Illuminate\Support\Facades\Validator;

/**
 * `LeadsImportDefinition::validateRow()`'s own-field rules (spec 0033
 * AC-011/012), extracted to stay under the 300-line soft limit
 * (engineering.md §6). Geo is validated by GeoRecognizer at staging, not
 * here (AC-005): only a row's own identity/contact format is this class'
 * concern.
 *
 * $mapped is the field-id-keyed value set AFTER recognizers ran (mapped
 * values merged with NameSplitRecognizer/GeoRecognizer output — the same
 * shape `resolveDuplicate()` receives).
 *
 * Spec 0108 (D-4) adds the campaign check, the one place a row is rejected
 * for its campaign: a run taking the campaign from a file column stages every
 * row with a `campaign_code` KEY (empty cell included), so its presence — not
 * a new parameter — tells the two modes apart. A Lead structurally cannot
 * exist without a campaign, hence an error and never a warning/placeholder.
 *
 * Spec 0176 (D-5) adds the source check: the run's Fonte, else the row
 * campaign's; a row left with neither is rejected, the lead requiring one.
 */
final class LeadRowValidator
{
    public function __construct(private readonly LeadSourceResolver $sourceResolver) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig  the run's configuration-step values
     * @return array<int, string> motivated error messages; empty = row accepted
     */
    public function validate(array $mapped, array $globalConfig = []): array
    {
        $errors = [];

        if (! $this->hasUsableIdentity($mapped)) {
            $errors[] = 'A row needs a first name + last name, a company name, or at least one contact (email or phone).';
        }

        return array_merge($errors, $this->campaignErrors($mapped), $this->sourceErrors($mapped, $globalConfig), $this->contactFormatErrors($mapped));
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, string>
     */
    private function campaignErrors(array $mapped): array
    {
        if (! array_key_exists(CampaignRecognizer::CAMPAIGN_CODE_FIELD, $mapped)) {
            return [];
        }

        $code = CampaignRecognizer::normalizeCode($mapped[CampaignRecognizer::CAMPAIGN_CODE_FIELD]);

        if ($code === null) {
            return ['The campaign code is empty: this import reads the campaign from a file column, so every row needs one.'];
        }

        if (($mapped[CampaignRecognizer::CAMPAIGN_ID_FIELD] ?? null) === null) {
            return ["No campaign matches the code \"{$code}\"."];
        }

        return [];
    }

    /**
     * A row whose campaign is unresolved is already rejected by
     * campaignErrors(): the source is only checked once there is a campaign
     * to inherit it from.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, string>
     */
    private function sourceErrors(array $mapped, array $globalConfig): array
    {
        $campaignId = LeadRowCampaign::resolve($mapped, $globalConfig);

        if ($campaignId === null) {
            return [];
        }

        $sourceId = $globalConfig['source_id'] ?? null;
        $sourceId = $sourceId === null || $sourceId === '' ? null : (int) $sourceId;

        if ($this->sourceResolver->resolve($sourceId, $campaignId) !== null) {
            return [];
        }

        return ['The source is missing: set it in the import configuration or on the campaign.'];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function hasUsableIdentity(array $mapped): bool
    {
        $hasName = $this->value($mapped, 'first_name') !== null && $this->value($mapped, 'last_name') !== null;
        $hasCompany = $this->value($mapped, 'company_name') !== null;
        $hasContact = array_any(
            array_keys(LeadContactFields::map()),
            fn (string $field): bool => $this->value($mapped, $field) !== null,
        );

        return $hasName || $hasCompany || $hasContact;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, string>
     */
    private function contactFormatErrors(array $mapped): array
    {
        $errors = [];

        foreach (LeadContactFields::map() as $field => $type) {
            $value = $this->value($mapped, $field);

            if ($value !== null && ! $this->isValidContactValue($type, $value)) {
                $errors[] = "The {$field} value \"{$value}\" is not valid.";
            }
        }

        return $errors;
    }

    private function isValidContactValue(ContactTypeEnum $type, string $value): bool
    {
        return Validator::make(['value' => $value], ['value' => $type->valueRules()])->passes();
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function value(array $mapped, string $field): ?string
    {
        $value = trim((string) ($mapped[$field] ?? ''));

        return $value === '' ? null : $value;
    }
}
