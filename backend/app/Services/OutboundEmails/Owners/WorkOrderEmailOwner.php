<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Enums\EmailTemplateModule;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DocumentLayouts\QuoteDocumentLayoutResolver;
use App\Services\OutboundEmails\EmailOwner;
use App\Services\OutboundEmails\WorkOrderEmailVariableCatalog;
use App\Services\OutboundEmails\WorkOrderEmailVariableResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * The Commessa as an email owner (spec 0175, generalized by spec 0195, D-10):
 * recipient suggestions, importable documents/quote PDF, variable catalogue
 * and rendering -- the work-order specific code the shared services used to
 * hold. Eager-loads every relation it touches up front (Model::
 * preventLazyLoading() is active outside production).
 */
final class WorkOrderEmailOwner implements EmailOwner
{
    private const string DOCUMENTS_COLLECTION = 'documents';

    /** @var array<int, string> */
    private const array IMPORT_SOURCES = ['documents', 'document_bundle', 'quote_pdf'];

    /** @var array<int, string> */
    private const array RELATIONS = [
        'supervisors',
        'participants',
        'quote.opportunity.registry.personalData.contacts',
        'quote.opportunity.registry.referents.personalData.contacts',
        'quote.opportunity.referent.personalData.contacts',
    ];

    public function __construct(
        private readonly QuoteDocumentLayoutResolver $layoutResolver,
        private readonly WorkOrderEmailAttachmentImporter $importer,
        private readonly WorkOrderEmailVariableCatalog $catalog,
        private readonly WorkOrderEmailVariableResolver $resolver,
        private readonly ContactSuggestionBuilder $contacts,
    ) {}

    public function alias(): string
    {
        return 'work_order';
    }

    public function viewAbility(): string
    {
        return 'viewEmails';
    }

    public function sendAbility(): string
    {
        return 'sendEmail';
    }

    public function templateModule(): EmailTemplateModule
    {
        return EmailTemplateModule::WorkOrders;
    }

    public function defaultTo(Model $owner): array
    {
        return [];
    }

    /**
     * @return array{documents: array<int, array<string, mixed>>, quote_pdf_available: bool}
     */
    public function composeContextExtras(Model $owner, User $actor): array
    {
        $workOrder = $this->workOrder($owner);
        $workOrder->loadMissing(self::RELATIONS);

        $quote = $workOrder->quote;

        return [
            'documents' => $this->documents($workOrder, $quote?->opportunity?->registry, $actor),
            'quote_pdf_available' => $quote !== null && $actor->can('view', $quote) && $this->layoutResolver->resolve($quote) !== null,
        ];
    }

    public function importSources(): array
    {
        return self::IMPORT_SOURCES;
    }

    public function import(Model $owner, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail
    {
        return $this->importer->import($this->workOrder($owner), $source, $data, $email, $actor);
    }

    public function variableCatalog(User $actor): array
    {
        return $this->catalog->categoriesFor($actor);
    }

    public function render(string $subject, string $body, Model $owner, User $actor): array
    {
        return $this->resolver->render($subject, $body, $this->workOrder($owner), $actor);
    }

    private function workOrder(Model $owner): WorkOrder
    {
        assert($owner instanceof WorkOrder);

        return $owner;
    }

    public function recipientSuggestions(Model $owner, User $actor): array
    {
        $workOrder = $this->workOrder($owner);
        $workOrder->loadMissing(self::RELATIONS);
        $registry = $workOrder->quote?->opportunity?->registry;

        /** @var array<string, array{email: string, label: string, source: string}> $byEmail */
        $byEmail = [];

        if ($registry !== null && $actor->can('registries.view')) {
            $this->contacts->merge($byEmail, $this->contacts->fromPersonalData($registry->personalData, $registry->name, 'registry'));
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
                $this->contacts->merge($byEmail, $this->contacts->fromPersonalData($referent->personalData, $referent->name, 'referent'));
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
