<?php

declare(strict_types=1);

namespace App\Tables\Quotes;

use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteService;
use App\Services\Table\FormRequestCellValidator;
use App\Support\ManagerPositions;

/**
 * The write behind the quotes grid's inline cell edit (spec 0206, D-2): the
 * cell's field goes through UpdateQuoteRequest — rules, company-site and
 * reward cross-checks, workflow-status set, field permissions — and then
 * QuoteService::update(), so a role change resyncs the line commissions and
 * retargets the rewards exactly as from the detail.
 */
final class QuoteCellWriter
{
    private const string ROUTE_PARAMETER = 'quote';

    private const string MANAGER_SLOTS_FIELD = 'manager_slots';

    private const string COMPANY_FIELD = 'company_id';

    private const string COMPANY_SITE_FIELD = 'company_site_id';

    private const string WORKFLOW_STATUS_FIELD = 'quote_workflow_status_id';

    public function __construct(
        private readonly FormRequestCellValidator $formRequest,
        private readonly QuoteService $service,
    ) {}

    public function write(Quote $quote, string $fieldKey, mixed $value, User $actor, ?string $note): Quote
    {
        // Step 1: the payload the form itself would send for this one change.
        $payload = $this->payloadFor($quote, $fieldKey, $value, $note);

        // Step 2: the form's own rules, then its own service.
        $request = $this->formRequest->validate(UpdateQuoteRequest::class, self::ROUTE_PARAMETER, $quote, $actor, $payload);

        return $this->service->update($quote, $request->toData(), $actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(Quote $quote, string $fieldKey, mixed $value, ?string $note): array
    {
        return match ($fieldKey) {
            // D-3: the cell holds a list of people, the form a positional team.
            self::MANAGER_SLOTS_FIELD => [$fieldKey => ManagerPositions::slotsFor($quote, $value)],
            // D-5: like the form, a different company clears the site.
            self::COMPANY_FIELD => $this->companyPayload($quote, $value),
            // D-6: the transition note travels with the status, as in the form.
            self::WORKFLOW_STATUS_FIELD => [$fieldKey => $value, 'note' => $note],
            default => [$fieldKey => $value],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Quote $quote, mixed $companyId): array
    {
        $changed = $quote->company_id !== $companyId && $quote->company_site_id !== null;

        return $changed
            ? [self::COMPANY_FIELD => $companyId, self::COMPANY_SITE_FIELD => null]
            : [self::COMPANY_FIELD => $companyId];
    }
}
