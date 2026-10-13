<?php

namespace App\Tables;

use App\Models\ApiClient;
use App\Models\User;
use App\Tables\ApiClients\ApiClientColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `api-clients` domain (spec 0209).
 *
 * `last_used_at` is the max over the client's tokens, resolved by `withMax`
 * in baseQuery (no N+1) and sorted through its fixed alias, never an input
 * string. Visibility is `api-clients.view` (ApiClientPolicy::viewAny).
 */
class ApiClientsTableDefinition extends AbstractTableDefinition
{
    /** Alias produced by withMax('tokens', 'last_used_at'). */
    private const string LAST_USED_ALIAS = 'tokens_max_last_used_at';

    public function domain(): string
    {
        return 'api-clients';
    }

    /**
     * @return class-string<ApiClient>
     */
    public function modelClass(): string
    {
        return ApiClient::class;
    }

    /**
     * @return Builder<ApiClient>
     */
    public function baseQuery(): Builder
    {
        return ApiClient::query()->with('creator')->withMax('tokens', 'last_used_at');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ApiClientColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ApiClientColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ApiClientColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
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
     * @param  Builder<ApiClient>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'last_used_at') {
            return false;
        }

        $query->orderBy(self::LAST_USED_ALIAS, $direction);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ApiClient $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'is_active' => $row->is_active,
            'expires_at' => $row->expires_at,
            'key_last_four' => $row->key_last_four,
            'last_used_at' => $row->getAttribute(self::LAST_USED_ALIAS),
            'created_by' => $row->creator?->name,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Row action keys, each gated by ApiClientPolicy (edit and rotate-key
     * ride `update`).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $gate = Gate::forUser($actor);
        $allowed = [];

        if ($gate->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if ($gate->allows('update', $row)) {
            array_push($allowed, 'edit', 'rotate-key');
        }

        if ($gate->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        return $allowed;
    }
}
