<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Tables\EmailTemplates\EmailTemplateColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `email-templates` domain (spec 0175, D-14).
 *
 * Every column (name, module, subject, description, is_active, created_at)
 * is a real DB column handled entirely by the generic engine.
 */
class EmailTemplatesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly EmailTemplateService $service) {}

    public function domain(): string
    {
        return 'email-templates';
    }

    /**
     * @return class-string<EmailTemplate>
     */
    public function modelClass(): string
    {
        return EmailTemplate::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives EmailTemplatePolicy::viewAny
    // from modelClass() (email-templates.viewAny).

    /**
     * @return Builder<EmailTemplate>
     */
    public function baseQuery(): Builder
    {
        return EmailTemplate::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return EmailTemplateColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return EmailTemplateColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return EmailTemplateColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'name', 'direction' => 'asc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map an EmailTemplate to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var EmailTemplate $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'module' => $row->module->value,
            'subject' => $row->subject,
            'description' => $row->description,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via EmailTemplatePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var EmailTemplate $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to EmailTemplateService::delete() so the generic bulk-delete
     * endpoint respects the SAME path as the single DELETE
     * /email-templates/{emailTemplate} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var EmailTemplate $model */
        $this->service->delete($model);
    }
}
