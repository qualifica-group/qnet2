<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasEmployment;
use App\Models\Concerns\HasPersonalData;
use App\Models\Concerns\LogsModelActivity;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'locale', 'is_active', 'ui_scale', 'date_format', 'time_format'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasApiTokens, HasAttachments, HasCustomFields, HasEmployment, HasFactory, HasPersonalData, HasRoles, LogsModelActivity, Notifiable;

    /**
     * Attachment collection that holds the user's avatar. A user keeps at most
     * one avatar: uploading a new one replaces the previous (see AvatarService).
     */
    public const string AVATAR_COLLECTION = 'avatar';

    /**
     * The user's current avatar (latest file in the avatar collection), if any.
     * Eager-loadable to avoid N+1 when exposing avatars.
     */
    public function avatar(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('collection', self::AVATAR_COLLECTION)
            ->latestOfMany();
    }

    /**
     * Limit the query to users that own a personal-data card — i.e. the users
     * that take part in the personal-data flow. The card is mandatory for these
     * users, so this is the canonical way to target them (e.g. in seeders),
     * instead of matching a hardcoded email.
     */
    #[Scope]
    protected function withPersonalData(Builder $query): void
    {
        $query->whereHas('personalData');
    }

    /**
     * The current avatar as an inline `data:` URI (base64), or null when unset.
     *
     * Embedding the image in the API resource lets the frontend render it
     * directly in an <img>, without a second authenticated request to the
     * private attachment download endpoint. Returns null if the file row is
     * missing on disk so a broken reference never breaks the response.
     */
    public function avatarDataUri(): ?string
    {
        $avatar = $this->avatar;

        if (! $avatar) {
            return null;
        }

        $disk = Storage::disk($avatar->disk);

        if (! $disk->exists($avatar->path)) {
            return null;
        }

        return 'data:'.$avatar->mime_type.';base64,'.base64_encode($disk->get($avatar->path));
    }

    /**
     * The leads this user operates as their assigned operator (spec 0024,
     * BR-2/D-4: restrict-on-delete — UserService::delete() guards on this
     * before deleting).
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'operator_id');
    }

    /**
     * The opportunities this user supervises (spec 0040, BR-3: restrict-on-
     * delete — UserService::delete() guards on this before deleting).
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunitiesAsSupervisor(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'supervisor_id');
    }

    /**
     * The opportunities this user manages as "Gestore Account" — the inverse
     * of Opportunity::managers() over the same `opportunity_user` pivot.
     * Read-side only (UserService::forSelect scopes the Supervisore picker to
     * a given opportunity's GA): the pivot is written exclusively from the
     * Opportunity side.
     *
     * @return BelongsToMany<Opportunity, $this>
     */
    public function managedOpportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_user')->withPivot('position');
    }

    /**
     * The user's preferred locale, used to localize every notification sent to
     * them (Laravel switches locale automatically via HasLocalePreference).
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Send the password reset link using the custom localized notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            // Spec 0013 — external data migration: the source system's id for
            // a migrated user, guarded (not in $fillable) so it is only ever
            // set by property assignment post-create, never mass-assigned.
            'old_id' => 'integer',
            // Spec 0042 — per-user module open mode preference. Guarded (not in
            // $fillable) on purpose: only the self-service /auth/me flow may set
            // it, via forceFill() (AuthService::updateProfile), never mass assignment.
            'module_open_preferences' => 'array',
            // Per-user UI scale (0..100 slider). A plain display preference like
            // `locale`: fillable, validated 0..100 by UpdateProfileRequest, no
            // escalation risk.
            'ui_scale' => 'integer',
        ];
    }
}
