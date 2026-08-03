<?php

namespace App\Http\Requests\Auth;

use App\Enums\DateFormatEnum;
use App\Enums\LocaleEnum;
use App\Enums\TimeFormatEnum;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PATCH /api/auth/me (self-service profile, ADR 0013).
 *
 * Reuses ValidatesUserProfile verbatim so the nested personal_data rules are the
 * SAME as PATCH /api/users/{user} (ADR 0012) — one source of truth, no fork. The
 * profile is optional here (update semantics): absent → card untouched.
 *
 * `name` is intentionally NOT accepted: on parity with the Users module the name
 * is derived from the submitted personal_data card (ProfileWriter via
 * CreatePersonalData::displayName()). roles / password / personable_* are not
 * accepted either; the owner is forced server-side to the authenticated user.
 *
 * `email` is READ-ONLY here (product decision): it is the registration email and
 * cannot be changed via self-service. GET /api/auth/me still exposes it; any email
 * sent in this payload is silently ignored, never validated nor persisted. The
 * Users module keeps managing email via its own requests, unaffected.
 */
class UpdateProfileRequest extends FormRequest
{
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Self-service: ownership by construction (always the authenticated user).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'locale' => ['sometimes', 'required', Rule::in(LocaleEnum::values())],

            // Per-user UI scale slider (0..100). A plain display preference,
            // clamped server-side to the 0..100 range the client exposes.
            'ui_scale' => ['sometimes', 'integer', 'between:0,100'],

            // Per-user date/time display preferences. Plain display fields like
            // `locale`: the accepted set is owned by the enums, the rendering by
            // the client formatter.
            'date_format' => ['sometimes', 'required', Rule::in(DateFormatEnum::values())],
            'time_format' => ['sometimes', 'required', Rule::in(TimeFormatEnum::values())],

            // Spec 0042 — per-user module open mode preference. `mode` is
            // required only when the object itself is submitted; override
            // keys are checked against the table registry in withValidator().
            'module_open_preferences' => ['sometimes', 'nullable', 'array'],
            'module_open_preferences.mode' => [
                Rule::requiredIf($this->has('module_open_preferences')),
                Rule::in(['modal', 'page', 'custom']),
            ],
            'module_open_preferences.overrides' => ['sometimes', 'nullable', 'array'],
            'module_open_preferences.overrides.*' => [Rule::in(['modal', 'page'])],
        ], $this->profileRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile (ADR
     * 0012), then the module-open-preferences override key allow-list.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateProfile($validator));
        $validator->after(fn (Validator $validator) => $this->validateModuleOpenPreferences($validator));
    }

    /**
     * Every `module_open_preferences.overrides` key must be a domain the
     * generic table registry actually knows (config/tables.php), excluding the
     * non-CRUD ones that are never commutable (spec 0042, scope §out). Read
     * from the registry itself so a newly registered module is switchable
     * without touching this request.
     */
    protected function validateModuleOpenPreferences(Validator $validator): void
    {
        $overrides = (array) $this->input('module_open_preferences.overrides', []);

        if ($overrides === []) {
            return;
        }

        $validDomains = $this->switchableModuleDomains();

        foreach (array_keys($overrides) as $domain) {
            if (! in_array($domain, $validDomains, true)) {
                $validator->errors()->add(
                    "module_open_preferences.overrides.{$domain}",
                    "The selected module [{$domain}] is invalid.",
                );
            }
        }
    }

    /**
     * The module domains a user may target with an override: every domain
     * registered in config/tables.php except the non-CRUD ones (import-runs'
     * wizard, and any future migrations preview).
     *
     * @return array<int, string>
     */
    protected function switchableModuleDomains(): array
    {
        /** @var array<string, class-string> $definitions */
        $definitions = config('tables.definitions', []);

        return array_values(array_diff(array_keys($definitions), ['import-runs', 'migrations']));
    }

    /**
     * Only the account fields the client actually submitted, ready for a partial
     * mass-assignment update. The name is derived from the card (not here), and
     * any forbidden field is dropped by the validator before this runs.
     *
     * @return array<string, mixed>
     */
    public function accountAttributes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_filter(
            [
                'locale' => $validated['locale'] ?? null,
                'ui_scale' => $validated['ui_scale'] ?? null,
                'date_format' => $validated['date_format'] ?? null,
                'time_format' => $validated['time_format'] ?? null,
            ],
            static fn ($value): bool => $value !== null,
        );
    }

    /**
     * The submitted `module_open_preferences`, or null when the client did not
     * send the key (leave the stored preference untouched). Kept OUT of
     * accountAttributes() on purpose: the column is guarded (not in
     * User::$fillable — spec 0042, AC-008), so the service assigns this value
     * via forceFill() rather than through mass assignment.
     *
     * @return array{mode: string, overrides: array<string, string>}|null
     */
    public function moduleOpenPreferences(): ?array
    {
        if (! $this->has('module_open_preferences')) {
            return null;
        }

        /** @var array{mode: string, overrides?: array<string, string>} $validated */
        $validated = $this->validated()['module_open_preferences'];

        return [
            'mode' => $validated['mode'],
            'overrides' => $validated['overrides'] ?? [],
        ];
    }
}
