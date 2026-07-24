<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasPersonalData;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CompanySiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

/**
 * Company site entity (spec 0020 — "Società Sedi"): a flexible site
 * anagraphic under a Company. Reuses the users/referents anagraphic stack
 * unchanged via `HasPersonalData` (morph `personable`): the site owns a
 * personal-data card, which in turn owns its own contacts/addresses — the
 * SAME discipline as the Registry module. A polymorphic logo (avatar pattern)
 * and an owned bank list via a real FK (not a morph) complete it.
 *
 * Unlike Registry, `name` is the site's OWN required column (not derived from
 * the card).
 *
 * The former "Altro" section attributes AND the client-specific ERP settings
 * (responsible_*, proforma/invoice progressives, quotation_*) are no longer
 * columns/attributes here: they are universal custom fields (spec 0021),
 * provisioned by QualificaTemplateSeeder. Only `company_id` (the owning
 * società) remains a native attribute.
 */
class CompanySite extends BaseModel
{
    /** @use HasFactory<CompanySiteFactory> */
    use HasAttachments, HasFactory, HasPersonalData, LogsModelActivity;

    /**
     * Attachment collection that holds the site's logo. A site keeps at most
     * one logo: uploading a new one replaces the previous (see LogoService).
     */
    public const string LOGO_COLLECTION = 'logo';

    protected $fillable = [
        'name', 'notes', 'is_default', 'company_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        // Spec 0013 — external data migration: guarded (not in $fillable), only
        // ever set by property assignment post-create.
        'old_id' => 'integer',
    ];

    public function banks(): HasMany
    {
        return $this->hasMany(CompanySiteBank::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The site's current logo (latest file in the logo collection), if any.
     * Eager-loadable to avoid N+1 when exposing logos (mirrors User::avatar()).
     */
    public function logo(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('collection', self::LOGO_COLLECTION)
            ->latestOfMany();
    }

    /**
     * The current logo as an inline `data:` URI (base64), or null when unset
     * or the file is missing on disk (mirrors User::avatarDataUri()).
     */
    public function logoDataUri(): ?string
    {
        $logo = $this->logo;

        if (! $logo) {
            return null;
        }

        $disk = Storage::disk($logo->disk);

        if (! $disk->exists($logo->path)) {
            return null;
        }

        return 'data:'.$logo->mime_type.';base64,'.base64_encode($disk->get($logo->path));
    }
}
