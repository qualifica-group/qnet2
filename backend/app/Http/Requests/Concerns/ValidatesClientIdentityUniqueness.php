<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Registry;
use App\Rules\UniquePersonalDataIdentifier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The identity-uniqueness gate for the `client_*` branch of the
 * request-management create payload (user directive 2026-09-09).
 *
 * That branch creates a brand-new Anagrafica, so it must obey the same
 * constraint the Anagrafica form itself obeys (directive 2026-08-06): a codice
 * fiscale, a partita IVA or a phone number already held by a user, an
 * anagrafica or a referente blocks the write. Without this, "Gestione
 * Richieste" was the one door into the namespace with no lock on it — the very
 * duplicates the other forms refuse could be created from here.
 *
 * The owner is always a brand-new Registry: nothing to exclude from the
 * lookup, and the EXISTING-registry branch of the payload (`registry_id`)
 * never reaches these rules, since D-2 prohibits the `client_*` blocks there.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesClientIdentityUniqueness
{
    use ValidatesPhoneUniqueness;

    /**
     * The fiscal columns of the client card, allow-listed for
     * `UniquePersonalDataIdentifier` (which interpolates the column name).
     *
     * @var array<int, string>
     */
    private const array CLIENT_FISCAL_COLUMNS = ['tax_code', 'vat_number'];

    /**
     * Appends the fiscal uniqueness rules to the client identity fields the
     * shape rules already declare — appended, never assigned, so the format
     * rules (`TaxCode`/`VatNumber`) stay in place.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    protected function withClientIdentityUniquenessRules(array $rules): array
    {
        foreach (self::CLIENT_FISCAL_COLUMNS as $column) {
            $rules["client_identity.{$column}"][] = new UniquePersonalDataIdentifier(
                $column,
                $this->identityUniquenessOwner() ?? Registry::class,
                $this->identityUniquenessOwnerId(),
            );
        }

        return $rules;
    }

    /**
     * The client's contacts do not live under a `personal_data` card on this
     * payload (see `ValidatesRequestClientProfile`).
     */
    protected function phoneUniquenessContactsKey(): string
    {
        return 'client_contacts';
    }

    /**
     * @return class-string<Registry>
     */
    protected function identityUniquenessOwner(): ?string
    {
        return Registry::class;
    }

    /**
     * Nothing to exclude: the client branch always creates a new anagrafica.
     */
    protected function identityUniquenessOwnerId(): ?int
    {
        return null;
    }

    /**
     * After-hook counterpart of the rules above: the fiscal columns are plain
     * rules, the contact rows need the payload-wide pass.
     */
    protected function validateClientIdentityUniqueness(Validator $validator): void
    {
        $this->validatePhoneUniqueness($validator);
    }
}
