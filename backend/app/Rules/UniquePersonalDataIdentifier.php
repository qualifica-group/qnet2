<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\ContactValueNormalizer;
use App\Support\IdentityUniquenessScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A fiscal identifier (`tax_code`/`vat_number`) may sit on at most one card of
 * the shared identity namespace (user directive 2026-08-06): a codice fiscale
 * or a partita IVA already held by a user, an anagrafica or a referente blocks
 * the write, whichever of the three carries it.
 *
 * The namespace itself lives in `IdentityUniquenessScope` — this rule only
 * decides WHICH column it interrogates.
 *
 * The comparison is normalized (upper + trim, ContactValueNormalizer::taxCode)
 * rather than a plain equality: `InputFormat` canonicalizes only what enters
 * through a FormRequest, while factory/migrated rows carry whatever shape the
 * source had — an exact match would let those through.
 */
final class UniquePersonalDataIdentifier implements ValidationRule
{
    /**
     * The identifier columns this rule may target, mapped to their failure
     * message. The value reaches `whereRaw` as a bound parameter, but the
     * COLUMN is interpolated into the SQL, so it is allow-listed here and can
     * never originate from request input (backend.md §8).
     *
     * @var array<string, string>
     */
    private const array MESSAGES = [
        'tax_code' => 'The tax code is already assigned to another record.',
        'vat_number' => 'The VAT number is already assigned to another record.',
    ];

    /**
     * @param  string  $column  one of the allow-listed identifier columns
     * @param  class-string<Model>  $ownerClass  the entity owning the card being written
     * @param  int|null  $ignoreOwnerId  the owner being updated — its own card never collides with itself
     */
    public function __construct(
        private readonly string $column,
        private readonly string $ownerClass,
        private readonly ?int $ignoreOwnerId = null,
    ) {
        if (! array_key_exists($column, self::MESSAGES)) {
            throw new InvalidArgumentException("Unsupported identifier column [{$column}].");
        }
    }

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if ($this->isTaken(ContactValueNormalizer::taxCode($value))) {
            $fail(__(self::MESSAGES[$this->column]));
        }
    }

    private function isTaken(string $normalized): bool
    {
        return IdentityUniquenessScope::cards($this->ownerClass, $this->ignoreOwnerId)
            ->whereNotNull($this->column)
            ->whereRaw("UPPER(TRIM({$this->column})) = ?", [$normalized])
            ->exists();
    }
}
