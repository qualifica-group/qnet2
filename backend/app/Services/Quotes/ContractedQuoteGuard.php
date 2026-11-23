<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Exceptions\CodedHttpException;
use App\Models\Quote;
use App\Models\User;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * An offer under contract is frozen (spec 0404 part E): every user write on it
 * (Offerte form and cells, Gestione Richieste/Iscritti panel and cells, title
 * and lines from the Contract) goes through run(). Internal writes (contract
 * validation, recalculations, migrations) never do.
 *
 * - no contract, or only operational fields (E-D3: team, next callback,
 *   operational site) -> the write runs as before;
 * - no `quotes.updateContracted` -> 403;
 * - the permission but no `confirm_contracted_edit` in the request -> 409
 *   `contracted_edit_confirmation_required`, the client asks and resends;
 * - confirmed -> the write runs and leaves a `contracted_edit` log entry.
 *
 * The confirmation is read from the ambient request, as the transition note of
 * the inline cell edit is (WritesQuoteCells).
 */
final class ContractedQuoteGuard
{
    public const string ABILITY = 'updateContracted';

    public const string CONFIRM_FIELD = 'confirm_contracted_edit';

    public const string CONFIRMATION_CODE = 'contracted_edit_confirmation_required';

    public const string EVENT = 'contracted_edit';

    private const string REGISTRY_FIELD = 'registry_id';

    private const string SYSTEM_STATUS_FIELD = 'quote_system_status_id';

    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * The format a submitted date string is compared in, by its length.
     *
     * @var array<int, string>
     */
    private const array DATE_FORMATS = [10 => 'Y-m-d', 16 => 'Y-m-d\\TH:i', 19 => self::DATE_TIME_FORMAT];

    /** Marks a key the offer has no stored value to compare with. */
    private const string UNKNOWN = "\0unknown";

    /**
     * Fields that stay writable on an offer under contract (E-D3), plus the
     * keys that only accompany another field.
     *
     * @var list<string>
     */
    private const array FREE_FIELDS = [
        'manager_slots',
        'managers',
        'promote_managers_to_opportunity',
        'next_callback_at',
        'operational_site_id',
        // The opportunity's "Note generali": notes are never frozen (E-D2).
        'general_notes',
        'note',
        self::CONFIRM_FIELD,
    ];

    /**
     * @template T
     *
     * @param  array<string, mixed>  $submitted  the payload the write submits
     * @param  Closure(): T  $write
     * @return T
     */
    public function run(Quote $quote, User $actor, array $submitted, Closure $write): mixed
    {
        // Step 1: is this a write the freeze covers?
        $frozenFields = $this->frozenFields($quote, $submitted);

        if ($frozenFields === [] || ! $quote->contract()->exists()) {
            return $write();
        }

        // Step 2: permission, then the explicit confirmation
        $this->assertConfirmed($actor);

        // Step 3: write, then leave the trace
        $result = $write();
        $this->log($quote, $actor, $frozenFields);

        return $result;
    }

    /**
     * Whether $actor may edit an offer under contract at all (the FE shows
     * the fields read-only otherwise).
     */
    public function mayEdit(User $actor): bool
    {
        return $actor->can('quotes.'.self::ABILITY);
    }

    /**
     * The submitted keys that would really change a frozen field: free fields
     * and values resent as they are (a whole form saved unchanged, the same
     * client picked again) do not count.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<int, string>
     */
    private function frozenFields(Quote $quote, array $submitted): array
    {
        $fields = [];

        foreach ($submitted as $field => $value) {
            if (! in_array($field, self::FREE_FIELDS, true) && ! $this->isUnchanged($quote, $field, $value)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function isUnchanged(Quote $quote, string $field, mixed $value): bool
    {
        if (! is_scalar($value) && $value !== null) {
            return false;
        }

        $current = match (true) {
            $field === self::REGISTRY_FIELD => $quote->opportunity()->value('registry_id'),
            $field === self::SYSTEM_STATUS_FIELD => $quote->quoteWorkflowStatus()->value('quote_system_status_id'),
            array_key_exists($field, $quote->getAttributes()) => $quote->getAttribute($field),
            default => self::UNKNOWN,
        };

        if ($current instanceof DateTimeInterface) {
            return $value !== null && $current->format(self::DATE_FORMATS[strlen((string) $value)] ?? self::DATE_TIME_FORMAT) === (string) $value;
        }

        return $current !== self::UNKNOWN && (string) self::scalar($current) === (string) $value;
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function assertConfirmed(User $actor): void
    {
        abort_unless($this->mayEdit($actor), Response::HTTP_FORBIDDEN, __('Quote under contract: editing is not allowed.'));

        if (request()->boolean(self::CONFIRM_FIELD)) {
            return;
        }

        throw new CodedHttpException(
            Response::HTTP_CONFLICT,
            self::CONFIRMATION_CODE,
            __('The quote is under contract: this change alters a document already accepted. Confirm to proceed.'),
        );
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function log(Quote $quote, User $actor, array $fields): void
    {
        activity($quote->getTable())
            ->performedOn($quote)
            ->causedBy($actor)
            ->event(self::EVENT)
            ->withProperties(['fields' => $fields])
            ->log('Quote under contract edited');
    }
}
