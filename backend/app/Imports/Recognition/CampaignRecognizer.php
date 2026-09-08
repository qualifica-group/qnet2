<?php

namespace App\Imports\Recognition;

use App\Imports\ImportRowContext;
use App\Models\Campaign;

/**
 * Resolves a row's mapped `campaign_code` (the campaign CODE printed on the
 * file, CMP-0001) to `campaign_id` — spec 0108, D-1/D-3. Mirrors
 * GeoRecognizer: the id it produces is NOT a mappable field of its own, it is
 * recognizer output merged into the row's `mapped_values`/`resolved`, where
 * LeadRowValidator (invalidating an unmatched row), LeadDuplicateMatcher and
 * LeadRowPersister read it back.
 *
 * A blank cell or an unknown code resolves NOTHING on purpose: the row-level
 * error belongs to LeadRowValidator (one place decides a row's fate, D-4), not
 * to a recognizer that would otherwise flag the row `warning` and let it
 * through.
 *
 * Registered `scoped` in AppServiceProvider: StagedRowBuilder resolves every
 * recognizer through the container once PER ROW, so without the shared
 * instance the code lookup below would run one query per row instead of one
 * per distinct code.
 */
final class CampaignRecognizer implements RowRecognizer
{
    public const string CAMPAIGN_CODE_FIELD = 'campaign_code';

    public const string CAMPAIGN_ID_FIELD = 'campaign_id';

    /** Normalized code => campaign id (null = looked up, does not exist). */
    private array $idsByCode = [];

    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult
    {
        // Step 1: no `campaign_code` KEY at all means this run takes its
        // campaign from the global config — nothing to resolve here.
        $code = self::normalizeCode($mapped[self::CAMPAIGN_CODE_FIELD] ?? null);

        if ($code === null) {
            return RecognitionResult::none();
        }

        // Step 2: an unknown code resolves nothing; the row is rejected by
        // LeadRowValidator, which sees the unresolved pair.
        $campaignId = $this->campaignIdFor($code);

        if ($campaignId === null) {
            return RecognitionResult::none();
        }

        // Step 3: the canonical code replaces whatever casing/spacing the file
        // carried, so the review grid shows the code as the system spells it.
        return RecognitionResult::resolved([
            self::CAMPAIGN_ID_FIELD => $campaignId,
            self::CAMPAIGN_CODE_FIELD => $code,
        ]);
    }

    /**
     * The comparable form of a campaign code: trimmed and upper-cased, so
     * ` cmp-0001 ` matches `CMP-0001` (AC-007). Null when there is nothing to
     * compare — the single place that decides what "blank" means for a code.
     */
    public static function normalizeCode(mixed $value): ?string
    {
        $code = mb_strtoupper(trim((string) $value));

        return $code === '' ? null : $code;
    }

    private function campaignIdFor(string $code): ?int
    {
        if (array_key_exists($code, $this->idsByCode)) {
            return $this->idsByCode[$code];
        }

        /** @var int|null $id */
        $id = Campaign::query()->where('code', $code)->value('id');

        return $this->idsByCode[$code] = $id === null ? null : (int) $id;
    }
}
