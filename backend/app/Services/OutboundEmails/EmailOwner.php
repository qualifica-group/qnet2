<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\DataObjects\WorkOrderEmails\ImportAttachmentsData;
use App\Enums\EmailTemplateModule;
use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What a record type must provide to own OutboundEmails (spec 0195, D-10):
 * one implementation per owner model, registered by morph alias in
 * `config/outbound_emails.php` (`owners`) and looked up through
 * EmailOwnerRegistry. The shared services (OutboundEmailService, compose
 * context, attachment import) work on the owner Model and delegate every
 * owner-specific rule here.
 */
interface EmailOwner
{
    /** Morph alias of the owner model (the registry key). */
    public function alias(): string;

    /** Policy ability name (checked via Gate on the owner) for history/read endpoints. */
    public function viewAbility(): string;

    /** Policy ability name (checked via Gate on the owner) for draft/attach/send endpoints. */
    public function sendAbility(): string;

    public function templateModule(): EmailTemplateModule;

    /**
     * @return array<int, array{email: string, label: string, source: string}>
     */
    public function recipientSuggestions(Model $owner, User $actor): array;

    /**
     * Pre-filled `to` of a new draft; empty when the owner has no default.
     *
     * @return array<int, string>
     */
    public function defaultTo(Model $owner): array;

    /**
     * Owner-specific keys merged into the compose-context response (e.g. the
     * importable documents). Never overrides the generic keys.
     *
     * @return array<string, mixed>
     */
    public function composeContextExtras(Model $owner, User $actor): array;

    /**
     * Attachment import `source` values this owner supports.
     *
     * @return array<int, string>
     */
    public function importSources(): array;

    /**
     * Copies the chosen source onto the draft (the draft-only guard and the
     * "source is supported" check are already done by the caller). Returns the
     * reloaded email.
     */
    public function import(Model $owner, string $source, ImportAttachmentsData $data, OutboundEmail $email, User $actor): OutboundEmail;

    /**
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    public function variableCatalog(User $actor): array;

    /**
     * @return array{subject: string, body: string}
     */
    public function render(string $subject, string $body, Model $owner, User $actor): array;
}
