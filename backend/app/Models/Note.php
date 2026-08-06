<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reusable, polymorphic, agnostic note (spec 0052, D-9): a discussion entry
 * attached to any owning model via `notable_type`/`notable_id`. AGNOSTIC by
 * design (AC-021) — this model and its relations know nothing about which
 * modules attach notes to themselves; that binding (which entity, which
 * read-gate, who is mentionable) lives entirely in config/notes.php, read by
 * App\Notes\NoteEntityRegistry.
 *
 * Thread is a single level (D-7): a note with `parent_id === null` is a
 * root, one with `parent_id` set is a flat reply to that root — never
 * enforced here, only by the writing service (normalization belongs to
 * NoteService, not the model).
 *
 * Mutability is tracked, not immutable (D-8): `edited_at` marks a body
 * change, deletion is soft (SoftDeletes) so history is preserved.
 *
 * `quote_id` (spec 0085, D-1) scopes the note to a single Offerta instead of
 * the whole host record — null means a general note. It is a scoping
 * column, not a second `notable`: DELIBERATELY absent from #[Fillable] and
 * immutable after creation (D-3), written only by NoteService.
 */
#[Fillable(['body'])]
class Note extends BaseModel
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The Offerta this note is scoped to (D-1), null for a general note.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function mentionedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'note_mentions');
    }
}
