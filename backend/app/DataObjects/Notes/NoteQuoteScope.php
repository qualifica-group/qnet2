<?php

namespace App\DataObjects\Notes;

/**
 * Parsed `quote_scope` for GET /api/notes (spec 0085, D-2): the raw query
 * string is exactly one of `'all'` (default), `'general'` or a numeric
 * Offerta id — never a free-form value, so parsing and the resulting
 * predicate stay 1:1 (data_contract: 'all' = no predicate, 'general' =
 * `quote_id IS NULL`, `<id>` = `quote_id = <id>`).
 */
final readonly class NoteQuoteScope
{
    private function __construct(
        public string $type,
        public ?int $quoteId,
    ) {}

    public static function all(): self
    {
        return new self('all', null);
    }

    public static function general(): self
    {
        return new self('general', null);
    }

    public static function quote(int $quoteId): self
    {
        return new self('quote', $quoteId);
    }

    /**
     * Assumes $raw already passed IndexNoteRequest's format/ownership
     * validation — this only maps the three well-known shapes, it never
     * re-validates them.
     */
    public static function fromParam(?string $raw): self
    {
        return match (true) {
            $raw === null, $raw === '', $raw === 'all' => self::all(),
            $raw === 'general' => self::general(),
            default => self::quote((int) $raw),
        };
    }
}
