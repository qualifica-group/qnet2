<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Enums\ContactTypeEnum;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\QuoteDocumentLayoutResolver;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * `GET /api/work-orders/{workOrder}/emails/compose-context` (spec 0175,
 * D-5/D-6/D-7/D-9): everything the composer needs besides the draft itself,
 * resolved ONCE server-side so the client never re-derives visibility
 * scoping. Eager-loads every relation it touches up front (Model::
 * preventLazyLoading() is active outside production) rather than accessing
 * them lazily one at a time.
 */
final class WorkOrderEmailComposeContextBuilder
{
    private const string DOCUMENTS_COLLECTION = 'documents';

    public function __construct(private readonly QuoteDocumentLayoutResolver $layoutResolver) {}

    /**
     * @return array{sender: array{name: string, email: string}, recipient_suggestions: array<int, array<string, string>>, documents: array<int, array<string, mixed>>, quote_pdf_available: bool, max_total_attachments_kb: int}
     */
    public function build(WorkOrder $workOrder, User $actor): array
    {
        $workOrder->loadMissing([
            'supervisors',
            'participants',
            'quote.opportunity.registry.personalData.contacts',
            'quote.opportunity.registry.referents.personalData.contacts',
            'quote.opportunity.referent.personalData.contacts',
        ]);

        $registry = $workOrder->quote?->opportunity?->registry;
        $quote = $workOrder->quote;

        return [
            'sender' => ['name' => (string) $actor->name, 'email' => (string) $actor->email],
            'recipient_suggestions' => $this->recipientSuggestions($workOrder, $registry, $actor),
            'documents' => $this->documents($workOrder, $registry, $actor),
            'quote_pdf_available' => $quote !== null && $actor->can('view', $quote) && $this->layoutResolver->resolve($quote) !== null,
            'max_total_attachments_kb' => (int) config('outbound_emails.max_total_attachments_kb'),
        ];
    }

    /**
     * @return array<int, array{email: string, label: string, source: string}>
     */
    private function recipientSuggestions(WorkOrder $workOrder, ?Registry $registry, User $actor): array
    {
        /** @var array<string, array{email: string, label: string, source: string}> $byEmail */
        $byEmail = [];

        if ($registry !== null && $actor->can('registries.view')) {
            $this->addSuggestions($byEmail, $this->contactSuggestions($registry->personalData, $registry->name, 'registry'));
        }

        if ($actor->can('referents.view')) {
            $referents = collect();

            if ($registry !== null) {
                $referents = $referents->merge($registry->referents);
            }

            if ($workOrder->quote?->opportunity?->referent !== null) {
                $referents->push($workOrder->quote->opportunity->referent);
            }

            foreach ($referents->unique('id') as $referent) {
                /** @var Referent $referent */
                $this->addSuggestions($byEmail, $this->contactSuggestions($referent->personalData, $referent->name, 'referent'));
            }
        }

        foreach ($workOrder->supervisors as $user) {
            $this->addUserSuggestion($byEmail, $user, __('outbound_emails.recipient_supervisor'), 'supervisor');
        }

        foreach ($workOrder->participants as $user) {
            $this->addUserSuggestion($byEmail, $user, __('outbound_emails.recipient_participant'), 'participant');
        }

        return array_values($byEmail);
    }

    /**
     * @param  array<string, array{email: string, label: string, source: string}>  $byEmail
     * @param  array<int, array{email: string, label: string, source: string}>  $suggestions
     */
    private function addSuggestions(array &$byEmail, array $suggestions): void
    {
        foreach ($suggestions as $suggestion) {
            $byEmail[mb_strtolower($suggestion['email'])] ??= $suggestion;
        }
    }

    /**
     * @param  array<string, array{email: string, label: string, source: string}>  $byEmail
     */
    private function addUserSuggestion(array &$byEmail, User $user, string $roleLabel, string $source): void
    {
        if ($user->email === null || $user->email === '') {
            return;
        }

        $byEmail[mb_strtolower($user->email)] ??= [
            'email' => $user->email,
            'label' => "{$user->name} ({$roleLabel})",
            'source' => $source,
        ];
    }

    /**
     * Email + PEC contacts of ONE personal-data card. `value`/`normalized_value`
     * are hidden by default on Contact (personal data) — made visible here,
     * for THIS response only, never persisted.
     *
     * @return array<int, array{email: string, label: string, source: string}>
     */
    private function contactSuggestions(?PersonalData $personalData, string $ownerName, string $source): array
    {
        if ($personalData === null) {
            return [];
        }

        return $personalData->contacts
            ->whereIn('type', [ContactTypeEnum::Email, ContactTypeEnum::Pec])
            ->makeVisible('value')
            ->map(fn (Contact $contact): array => [
                'email' => (string) $contact->value,
                'label' => "{$ownerName} ({$contact->type->label()})",
                'source' => $source,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, original_name: string, size: int, source: string}>
     */
    private function documents(WorkOrder $workOrder, ?Registry $registry, User $actor): array
    {
        /** @var Collection<int, array{id: int, original_name: string, size: int, source: string}> $documents */
        $documents = collect();

        if ($actor->can('viewDocuments', WorkOrder::class)) {
            $documents = $documents->merge($this->documentRows($workOrder->attachments(), 'work_order'));
        }

        if ($registry !== null && $actor->can('viewDocuments', Registry::class)) {
            $documents = $documents->merge($this->documentRows($registry->attachments(), 'registry'));
        }

        return $documents->values()->all();
    }

    /**
     * @return array<int, array{id: int, original_name: string, size: int, source: string}>
     */
    private function documentRows(MorphMany $relation, string $source): array
    {
        return $relation->where('collection', self::DOCUMENTS_COLLECTION)->get()
            ->map(fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'size' => $attachment->size,
                'source' => $source,
            ])
            ->all();
    }
}
