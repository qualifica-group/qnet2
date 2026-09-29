<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\EmailTemplates\CreateEmailTemplateData;
use App\DataObjects\EmailTemplates\UpdateEmailTemplateData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\EmailTemplate;
use App\Services\OutboundEmails\EmailHtmlSanitizer;
use Illuminate\Support\Collection;

/**
 * Business logic for the `email-templates` resource (spec 0175, D-14): a
 * reusable subject/body pair with `{category.key}` placeholders (D-4). The
 * controller stays thin; this Service is the single authority.
 *
 * `body` is ALWAYS sanitized here (EmailHtmlSanitizer, D-11) — never trusted
 * as-submitted — on both create and update.
 */
class EmailTemplateService
{
    /** @var array<int, string> */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'module', 'is_active'];

    public function __construct(private readonly EmailHtmlSanitizer $sanitizer) {}

    public function create(CreateEmailTemplateData $data): EmailTemplate
    {
        /** @var EmailTemplate $emailTemplate */
        $emailTemplate = EmailTemplate::create([
            ...$data->attributes(),
            'body' => $this->sanitizer->sanitize($data->body),
        ]);

        return $emailTemplate;
    }

    public function update(EmailTemplate $emailTemplate, UpdateEmailTemplateData $data): EmailTemplate
    {
        $attributes = $data->submittedAttributes();

        if (array_key_exists('body', $attributes)) {
            $attributes['body'] = $this->sanitizer->sanitize($attributes['body']);
        }

        // Unconditional save: fires the model's saved event even when no
        // native attribute changed (mirrors TaskImportanceService::update).
        $emailTemplate->fill($attributes)->save();

        return $emailTemplate->fresh();
    }

    public function delete(EmailTemplate $emailTemplate): void
    {
        $emailTemplate->delete();
    }

    /**
     * Minimal, searchable, paginated, is_active-only list for the for-select
     * standard (ADR 0011), narrowed to ONE `module` (data_contract: filtro
     * obbligatorio, EmailTemplateForSelectRequest requires it).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = EmailTemplate::query()
            ->select(self::FOR_SELECT_COLUMNS)
            ->where('module', $query->emailTemplateModule?->value)
            ->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, EmailTemplate> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated — bypasses search, the module
     * filter AND `is_active` (mirrors TaskImportanceService's own hydration).
     *
     * @param  Collection<int, EmailTemplate>  $page
     * @return Collection<int, EmailTemplate>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, EmailTemplate> $hydrated */
        $hydrated = EmailTemplate::query()
            ->select(self::FOR_SELECT_COLUMNS)
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
