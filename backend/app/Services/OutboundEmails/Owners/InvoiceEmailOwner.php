<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Enums\ContactTypeEnum;
use App\Enums\EmailTemplateModule;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\OutboundEmail;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Services\Invoices\InvoiceEmailVariableCatalog;
use App\Services\Invoices\InvoiceEmailVariableResolver;
use App\Services\OutboundEmails\EmailOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * The Invoice as an email owner (spec 0195, D-12): the customer's PEC/email
 * as default recipient and suggestions (plus its referents'), the invoice PDF
 * and the customer's documents as attachment sources, and the invoice
 * variable catalogue/rendering. Eager-loads what it reads up front
 * (Model::preventLazyLoading() is active outside production).
 */
final class InvoiceEmailOwner implements EmailOwner
{
    private const string DOCUMENTS_COLLECTION = 'documents';

    /** @var array<int, string> */
    private const array RELATIONS = [
        'customer.personalData.contacts',
        'customer.referents.personalData.contacts',
    ];

    public function __construct(
        private readonly ContactSuggestionBuilder $contacts,
        private readonly InvoiceEmailAttachmentImporter $importer,
        private readonly InvoiceEmailVariableCatalog $catalog,
        private readonly InvoiceEmailVariableResolver $resolver,
    ) {}

    public function alias(): string
    {
        return 'invoice';
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
        return EmailTemplateModule::Invoices;
    }

    /**
     * Primary PEC of the customer, else its primary email (D-12).
     */
    public function defaultTo(Model $owner): array
    {
        $invoice = $this->invoice($owner);
        $invoice->loadMissing(self::RELATIONS);
        $contacts = $invoice->customer?->personalData?->contacts;

        foreach ([ContactTypeEnum::Pec, ContactTypeEnum::Email] as $type) {
            $primary = $contacts?->first(fn ($contact): bool => $contact->type === $type && $contact->is_primary);

            if ($primary !== null) {
                return [(string) $primary->makeVisible('value')->value];
            }
        }

        return [];
    }

    public function recipientSuggestions(Model $owner, User $actor): array
    {
        $invoice = $this->invoice($owner);
        $invoice->loadMissing(self::RELATIONS);
        $registry = $invoice->customer;

        /** @var array<string, array{email: string, label: string, source: string}> $byEmail */
        $byEmail = [];

        if ($registry !== null && $actor->can('registries.view')) {
            $this->contacts->merge($byEmail, $this->contacts->fromPersonalData($registry->personalData, $registry->name, 'registry'));
        }

        if ($registry !== null && $actor->can('referents.view')) {
            foreach ($registry->referents as $referent) {
                /** @var Referent $referent */
                $this->contacts->merge($byEmail, $this->contacts->fromPersonalData($referent->personalData, $referent->name, 'referent'));
            }
        }

        return array_values($byEmail);
    }

    /**
     * @return array{documents: array<int, array{id: int, original_name: string, size: int, source: string}>, quote_pdf_available: bool, sources: array<int, string>}
     */
    public function composeContextExtras(Model $owner, User $actor): array
    {
        $invoice = $this->invoice($owner);
        $documents = [];

        if ($invoice->customer !== null && $actor->can('viewDocuments', Registry::class)) {
            $documents = $invoice->customer->attachments()->where('collection', self::DOCUMENTS_COLLECTION)->get()
                ->map(fn (Attachment $attachment): array => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'size' => $attachment->size,
                    'source' => 'registry',
                ])
                ->all();
        }

        return ['documents' => $documents, 'quote_pdf_available' => false, 'sources' => $this->importSources()];
    }

    public function importSources(): array
    {
        return [InvoiceEmailAttachmentImporter::SOURCE_PDF, InvoiceEmailAttachmentImporter::SOURCE_DOCUMENTS];
    }

    public function import(Model $owner, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail
    {
        return $this->importer->import($this->invoice($owner), $source, $data, $email, $actor);
    }

    public function variableCatalog(User $actor): array
    {
        return $this->catalog->categoriesFor($actor);
    }

    public function render(string $subject, string $body, Model $owner, User $actor): array
    {
        return $this->resolver->render($subject, $body, $this->invoice($owner), $actor);
    }

    private function invoice(Model $owner): Invoice
    {
        assert($owner instanceof Invoice);

        return $owner;
    }
}
