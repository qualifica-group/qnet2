<?php

declare(strict_types=1);

namespace App\Actions\Quotes;

use App\Http\Requests\Opportunities\StoreOpportunityRequest;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\OpportunityService;
use App\Services\QuoteService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Creates an Opportunity and its first Offerta in ONE transaction (spec 0396,
 * POST /api/quotes/with-opportunity). Each block is validated by the UNCHANGED
 * StoreOpportunityRequest / StoreQuoteRequest (rules, closures, field
 * permissions) run as sub-requests, and written by the same OpportunityService
 * / QuoteService the standalone endpoints use. Any failure rolls everything
 * back, so no orphan Opportunity survives a rejected Offerta.
 *
 * Errors of each block come back keyed `opportunity.<field>` / `quote.<field>`.
 */
final class CreateQuoteWithOpportunity
{
    private const string OPPORTUNITY_PREFIX = 'opportunity';

    private const string QUOTE_PREFIX = 'quote';

    public function __construct(
        private readonly OpportunityService $opportunityService,
        private readonly QuoteService $quoteService,
    ) {}

    /**
     * @param  array<string, mixed>  $opportunityInput  body of POST /api/opportunities
     * @param  array<string, mixed>  $quoteInput  body of POST /api/quotes, without `opportunity_id`
     */
    public function handle(Request $request, User $actor, array $opportunityInput, array $quoteInput): Quote
    {
        return DB::transaction(function () use ($request, $actor, $opportunityInput, $quoteInput): Quote {
            // Step 1: validate the opportunity block with the standalone rules.
            $opportunityData = $this->prefixed(
                self::OPPORTUNITY_PREFIX,
                fn () => $this->subRequest(StoreOpportunityRequest::class, $request, $opportunityInput)->toData(),
            );

            // Step 2: create it; the open-opportunity guard runs in the service.
            $opportunity = $this->prefixed(
                self::OPPORTUNITY_PREFIX,
                fn (): Opportunity => $this->opportunityService->create($opportunityData, $actor),
            );

            // Step 3: validate the quote block against the new opportunity id
            // (single-quote, manager appartenenza and line rules read it). The
            // GA team is promoted onto the new opportunity, as in Gestione Richieste.
            $quoteData = $this->prefixed(
                self::QUOTE_PREFIX,
                fn () => $this->subRequest(StoreQuoteRequest::class, $request, [
                    ...$quoteInput,
                    'opportunity_id' => $opportunity->id,
                    'promote_managers_to_opportunity' => true,
                ])->toData(),
            );

            // Step 4: create the quote through its service.
            return $this->prefixed(
                self::QUOTE_PREFIX,
                fn (): Quote => $this->quoteService->create($quoteData, $actor),
            );
        });
    }

    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $input
     * @return T
     */
    private function subRequest(string $class, Request $parent, array $input): FormRequest
    {
        $sub = $class::createFrom($parent);
        $sub->setJson(new InputBag($input));
        $sub->request = new InputBag($input);
        $sub->query = new InputBag;
        $sub->setContainer(app())->setRedirector(app('redirect'));
        $sub->setUserResolver(static fn () => $parent->user());
        $sub->validateResolved();

        return $sub;
    }

    /**
     * Runs $step and re-throws its ValidationException with every error key
     * namespaced by $prefix.
     *
     * @template T
     *
     * @param  Closure(): T  $step
     * @return T
     */
    private function prefixed(string $prefix, Closure $step): mixed
    {
        try {
            return $step();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors["{$prefix}.{$field}"] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
