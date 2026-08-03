<?php

namespace App\Http\Resources;

use App\Enums\DateFormatEnum;
use App\Enums\TimeFormatEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Default preference when the column is null (spec 0042): every module
     * opens exactly as it does today (custom mode with no override falls back
     * to each module's native defaultMode, resolved client-side). Never null
     * in the response.
     *
     * @var array{mode: string, overrides: array<string, string>}
     */
    private const array DEFAULT_MODULE_OPEN_PREFERENCES = ['mode' => 'custom', 'overrides' => []];

    /**
     * Default UI scale when the column is null: 40 on the 0..100 slider, which
     * the client maps to 100% (normal size). Never null in the response.
     */
    private const int UI_SCALE_DEFAULT = 40;

    /**
     * Default date/time display preferences when the columns are null: the
     * Italian day-first pattern on a 24-hour clock. Never null in the response.
     */
    private const string DATE_FORMAT_DEFAULT = DateFormatEnum::Dmy->value;

    private const string TIME_FORMAT_DEFAULT = TimeFormatEnum::H24->value;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            // Roles as {id, name} objects: the edit form drives the for-select
            // picker by id (ADR 0011) while the detail view still renders names —
            // both without a second lookup. `name` stays the membership identity.
            'roles' => $this->roles
                ->map(static fn ($role): array => [
                    'id' => (int) $role->id,
                    'name' => $role->name,
                ])
                ->values(),
            // Avatar embedded inline as a data: URI so the client renders it
            // directly, with no extra authenticated request (null when unset).
            'avatar_url' => $this->avatarDataUri(),
            // The nested personal-data tree, emitted only when the card is loaded
            // AND present (the nested user write returns it; an account-only user
            // or a plain read omits the key entirely) — ADR 0012.
            'personal_data' => $this->when(
                $this->relationLoaded('personalData') && $this->personalData !== null,
                fn () => new PersonalDataResource($this->personalData),
            ),
            // The nested employment tree (spec 0015), same discipline as
            // personal_data: emitted only when loaded AND present.
            'employment' => $this->when(
                $this->relationLoaded('employment') && $this->employment !== null,
                fn () => new EmploymentResource($this->employment),
            ),
            'created_at' => $this->created_at,
            // Spec 0042: mode/overrides for the per-user module open preference,
            // defaulted here (never null) when the column is unset.
            'module_open_preferences' => $this->module_open_preferences ?? self::DEFAULT_MODULE_OPEN_PREFERENCES,
            // Per-user UI scale (0..100), defaulted to 40 (=100%) when unset.
            'ui_scale' => $this->ui_scale ?? self::UI_SCALE_DEFAULT,
            // Per-user date/time display preferences, defaulted when unset.
            'date_format' => $this->date_format ?? self::DATE_FORMAT_DEFAULT,
            'time_format' => $this->time_format ?? self::TIME_FORMAT_DEFAULT,
        ];
    }
}
