<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails\Owners;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Enums\ContactTypeEnum;
use App\Enums\EmailTemplateModule;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\OutboundEmail;
use App\Models\PurchaseOrder;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Services\OutboundEmails\EmailOwner;
use App\Services\PurchaseOrders\PurchaseOrderEmailVariableCatalog;
use App\Services\PurchaseOrders\PurchaseOrderEmailVariableResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * The PurchaseOrder as an email owner (spec 0210): the supplier's primary
 * email (else PEC) as default recipient and suggestions (plus its
 * referents'), the order PDF and the supplier's documents as attachment
 * sources, and the order variable catalogue/rendering. Read with
 * `purchase-orders.view`, written/sent with `purchase-orders.send`.
 * Eager-loads what it reads up front (Model::preventLazyLoading() is active
 * outside production).
 */
final class PurchaseOrderEmailOwner implements EmailOwner
{
    private const string DOCUMENTS_COLLECTION = 'documents';

    /** @var array<int, string> */
    private const array RELATIONS = [
        'supplier.personalData.contacts',
        'supplier.referents.personalData.contacts',
    ];

    public function __construct(
        private readonly ContactSuggestionBuilder $contacts,
        private readonly PurchaseOrderEmailAttachmentImporter $importer,
        private readonly PurchaseOrderEmailVariableCatalog $catalog,
        private readonly PurchaseOrderEmailVariableResolver $resolver,
    ) {}

    public function alias(): string
    {
        return 'purchase_order';
    }

    public function viewAbility(): string
    {
        return 'view';
    }

    public function sendAbility(): string
    {
        return 'send';
    }

    public function templateModule(): EmailTemplateModule
    {
        return EmailTemplateModule::PurchaseOrders;
    }

    /**
     * Primary email of the supplier, else its primary PEC.
     */
    public function defaultTo(Model $owner): array
    {
        $order = $this->order($owner);
        $order->loadMissing(self::RELATIONS);
        $contacts = $order->supplier?->personalData?->contacts;

        foreach ([ContactTypeEnum::Email, ContactTypeEnum::Pec] as $type) {
            $primary = $contacts?->first(fn (Contact $contact): bool => $contact->type === $type && $contact->is_primary);

            if ($primary !== null) {
                return [(string) $primary->makeVisible('value')->value];
            }
        }

        return [];
    }

    public function recipientSuggestions(Model $owner, User $actor): array
    {
        $order = $this->order($owner);
        $order->loadMissing(self::RELATIONS);
        $registry = $order->supplier;

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
        $order = $this->order($owner);
        $documents = [];

        if ($order->supplier !== null && $actor->can('viewDocuments', Registry::class)) {
            $documents = $order->supplier->attachments()->where('collection', self::DOCUMENTS_COLLECTION)->get()
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
        return [PurchaseOrderEmailAttachmentImporter::SOURCE_PDF, PurchaseOrderEmailAttachmentImporter::SOURCE_DOCUMENTS];
    }

    public function import(Model $owner, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail
    {
        return $this->importer->import($this->order($owner), $source, $data, $email, $actor);
    }

    public function variableCatalog(User $actor): array
    {
        return $this->catalog->categoriesFor($actor);
    }

    public function render(string $subject, string $body, Model $owner, User $actor): array
    {
        return $this->resolver->render($subject, $body, $this->order($owner), $actor);
    }

    private function order(Model $owner): PurchaseOrder
    {
        assert($owner instanceof PurchaseOrder);

        return $owner;
    }
}
