<?php

namespace App\Models;

use App\Enums\EmailTemplateModule;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Email template lookup entity (spec 0175, D-10): a reusable subject/body
 * pair with `{categoria.chiave}` placeholders, resolved server-side by
 * `WorkOrderEmailVariableResolver` (BE-02) when the composer picks one — the
 * resolved text is then plain, editable copy on the draft OutboundEmail, not
 * ririsolto at send time (D-4). `body` is ALWAYS HTML sanitized with
 * `RichTextSanitizer` before persisting (D-11), same as `OutboundEmail::body`.
 *
 * `module` is present but frozen to `EmailTemplateModule::WorkOrders` in this
 * version (D-10): the column stays polymorphic-ready for Quote/Opportunity
 * templates without a migration. `old_id` is the legacy-migration anchor
 * (D-13), deliberately absent from #[Fillable] — set only by property
 * assignment post-create, mirroring `Source::old_id`.
 */
#[Fillable(['name', 'module', 'subject', 'body', 'description', 'is_active'])]
class EmailTemplate extends BaseModel
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module' => EmailTemplateModule::class,
            'is_active' => 'boolean',
            'old_id' => 'integer',
        ];
    }
}
