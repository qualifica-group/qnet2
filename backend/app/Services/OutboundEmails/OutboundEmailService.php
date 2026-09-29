<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\DataObjects\WorkOrderEmails\OutboundEmailDraftData;
use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\OutboundEmail;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Commessa email history/composer's core write path (spec 0175, D-2/D-3/
 * D-5/D-6/D-12): list, draft CRUD, send. WorkOrder-level authorization
 * (`work-orders.viewEmails`/`sendEmail` + membership scope) is the
 * CONTROLLER's job (`$this->authorize(...)` against WorkOrderPolicy, already
 * built) — this class only ever resolves and mutates ONE already-scoped
 * OutboundEmail.
 *
 * Draft VISIBILITY (D-3: a draft is visible only to its own author) is
 * enforced by `OutboundEmail::scopeVisibleTo()` on every lookup here, so a
 * hidden draft is simply never FOUND — resolveVisibleOrFail() throws the
 * same `ModelNotFoundException` a cross-commessa `{email}` would, both
 * mapped to a plain 404 by BaseApiController. This is a deliberate reading
 * of the frozen data_contract's literal "403 non autore" on GET/PATCH/DELETE:
 * given the visibility scope, that case is UNREACHABLE for a hidden draft —
 * a draft visibleTo() lets through is BY CONSTRUCTION the actor's own, so no
 * separate ownership check is needed for PATCH/DELETE beyond assertDraft().
 * SEND is the one case that genuinely needs an explicit authorship check
 * (403 `not_author`), because a `failed` email — unlike a draft — IS visible
 * to any actor with `viewEmails`, yet only its own author may resend it.
 */
final class OutboundEmailService
{
    private const int PER_PAGE = 20;

    public function __construct(
        private readonly EmailHtmlSanitizer $sanitizer,
        private readonly OutboundEmailAttachmentLimitChecker $limitChecker,
    ) {}

    /**
     * @return LengthAwarePaginator<int, OutboundEmail>
     */
    public function listForWorkOrder(WorkOrder $workOrder, User $actor, int $page): LengthAwarePaginator
    {
        return $this->baseQuery($workOrder, $actor)
            ->with('sender')
            ->withCount(['attachments as attachments_count' => fn ($query) => $query->where('collection', OutboundEmail::ATTACHMENT_COLLECTION)])
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE, page: $page);
    }

    /**
     * Every nested `{email}` endpoint's single entry point for resolving the
     * route's OutboundEmail: 404 for another commessa's email (emailable
     * scoping) OR a draft hidden from $actor (visibleTo scope) alike — see
     * class docblock.
     */
    public function resolveVisibleOrFail(WorkOrder $workOrder, int $emailId, User $actor): OutboundEmail
    {
        return $this->baseQuery($workOrder, $actor)->with($this->detailRelations())->findOrFail($emailId);
    }

    public function createDraft(WorkOrder $workOrder, User $actor, OutboundEmailDraftData $data): OutboundEmail
    {
        $email = new OutboundEmail($this->sanitizedAttributes($data));
        $email->emailable()->associate($workOrder);
        $email->sender_user_id = $actor->id;
        $email->status = OutboundEmailStatus::Draft;
        $email->save();

        return $email->fresh($this->detailRelations());
    }

    public function updateDraft(OutboundEmail $email, OutboundEmailDraftData $data): OutboundEmail
    {
        $this->assertDraft($email);

        $email->fill($this->sanitizedAttributes($data));
        $email->save();

        return $email->fresh($this->detailRelations());
    }

    /**
     * HasAttachments::bootHasAttachments() cascades the email's own allegati
     * automatically on this real delete (OutboundEmail carries no
     * SoftDeletes) — no separate cleanup call needed here.
     */
    public function deleteDraft(OutboundEmail $email): void
    {
        $this->assertDraft($email);

        $email->delete();
    }

    /**
     * Draft/failed -> queued (D-2/D-12). The job is dispatched as plain
     * sequential code AFTER the transaction returns (not `DB::afterCommit()`
     * or `->afterCommit()`): this method is always called from a thin
     * controller action with no outer transaction of its own, so the two are
     * behaviourally identical in production, and the plain form stays
     * observable under RefreshDatabase in tests (afterCommit callbacks
     * registered inside a test's own wrapping transaction never fire, since
     * that transaction is rolled back rather than committed at the end of
     * every test) — mirrors FieldChangeRequestCreator::handle().
     */
    public function send(OutboundEmail $email, User $actor): OutboundEmail
    {
        if ((int) $email->sender_user_id !== $actor->id) {
            abort(403, __('outbound_emails.not_author'));
        }

        if (! in_array($email->status, [OutboundEmailStatus::Draft, OutboundEmailStatus::Failed], true)) {
            abort(409, __('outbound_emails.not_sendable_status'));
        }

        $this->assertSendable($email, $actor);

        DB::transaction(fn () => $email->markQueued((string) $actor->email));

        SendOutboundEmailJob::dispatch($email);

        return $email->fresh($this->detailRelations());
    }

    /**
     * @return Builder<OutboundEmail>
     */
    private function baseQuery(WorkOrder $workOrder, User $actor): Builder
    {
        return OutboundEmail::query()
            ->where('emailable_type', $workOrder->getMorphClass())
            ->where('emailable_id', $workOrder->id)
            ->visibleTo($actor);
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function detailRelations(): array
    {
        return [
            'sender',
            'attachments' => fn ($query) => $query->where('collection', OutboundEmail::ATTACHMENT_COLLECTION),
        ];
    }

    private function assertDraft(OutboundEmail $email): void
    {
        if ($email->status !== OutboundEmailStatus::Draft) {
            abort(409, __('outbound_emails.not_draft'));
        }
    }

    /**
     * AC-012 field-by-field (D-12): `to` empty, `subject`/`body` blank (body
     * blank AFTER stripping tags -- an empty `<p></p>` from the editor must
     * not count as content), the actor's own mailbox missing, or the
     * attachments already on the draft exceeding the total-size ceiling.
     */
    private function assertSendable(OutboundEmail $email, User $actor): void
    {
        $errors = [];

        if (($email->to_recipients ?? []) === []) {
            $errors['to'] = [__('outbound_emails.no_recipients')];
        }

        if (trim((string) $email->subject) === '') {
            $errors['subject'] = [__('outbound_emails.empty_subject')];
        }

        if (trim(strip_tags((string) $email->body)) === '') {
            $errors['body'] = [__('outbound_emails.empty_body')];
        }

        if (empty($actor->email)) {
            $errors['sender'] = [__('outbound_emails.sender_email_missing')];
        }

        if ($this->limitChecker->currentBytes($email) > $this->limitChecker->maxBytes()) {
            $errors['attachments'] = [__('outbound_emails.attachments_limit_exceeded', [
                'max_kb' => (int) config('outbound_emails.max_total_attachments_kb'),
            ])];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizedAttributes(OutboundEmailDraftData $data): array
    {
        $attributes = $data->submittedAttributes();

        if (array_key_exists('body', $attributes) && $attributes['body'] !== null) {
            $attributes['body'] = $this->sanitizer->sanitize($attributes['body']);
        }

        return $attributes;
    }
}
