<?php

namespace App\Imports\RequestManagement;

use App\Imports\ImportRowContext;
use App\Imports\Recognition\RecognitionResult;
use App\Imports\Recognition\RowRecognizer;

/**
 * Reads the request-only cells of a row (spec 0348) into the values the commit
 * phase needs, running after the shared name/geo recognizers:
 *   - phone / extra_phones / email: split and validated (a rejected value is not
 *     stored);
 *   - next_callback_at: the callback date at `callback_time`;
 *   - operator_id: the operator named in the file;
 *   - product_ids: the product of the chosen category the text stands for;
 *   - attribute_values: the "Informazioni aggiuntive" cells, typed and checked.
 * Whatever cannot be stored is listed in `discarded_values` — the notes keep it —
 * and raises a warning: the row itself is never rejected for it.
 */
final class RequestRowRecognizer implements RowRecognizer
{
    public function __construct(
        private readonly RequestContactCleaner $contacts,
        private readonly ImportDateParser $dates,
        private readonly OperatorNameResolver $operators,
        private readonly ProductNameResolver $products,
        private readonly RequestAttributeValues $attributes,
        private readonly RequestImportLookups $lookups,
    ) {}

    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult
    {
        $resolved = [];
        $discarded = [];
        $messages = [];

        // Step 0: a client with no name at all gets the placeholder; a company needs none.
        $this->readIdentity($mapped, $resolved, $messages);

        // Step 1: contacts, so an unusable value never reaches the Registry.
        $this->readContacts($mapped, $resolved, $discarded, $messages);

        // Step 2: the planned callback.
        $this->readCallback($mapped, $resolved, $discarded, $messages);

        // Step 3: operator and product, by the names typed in the file.
        $this->readOperator($mapped, $resolved, $discarded, $messages);
        $this->readReporter($mapped, $resolved, $discarded, $messages);
        $this->readProduct($mapped, (int) ($context->globalConfig['product_category_id'] ?? 0), $resolved, $discarded, $messages);

        // Step 4: "Informazioni aggiuntive".
        $this->readAttributes($mapped, (int) ($context->globalConfig['product_category_id'] ?? 0), $resolved, $discarded, $messages);

        $resolved['discarded_values'] = $discarded;

        return RecognitionResult::resolved($resolved, needsReview: $messages !== [], messages: $messages);
    }

    /**
     * The engine would default a blank first/last name to the placeholder even
     * for a company row, where it is only noise: the domain does it here instead,
     * for rows that name no company.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, string>  $messages
     */
    private function readIdentity(array $mapped, array &$resolved, array &$messages): void
    {
        if (trim((string) ($mapped['company_name'] ?? '')) !== '') {
            return;
        }

        $placeholder = (string) config('imports.placeholder');

        foreach (['first_name', 'last_name'] as $field) {
            if (trim((string) ($mapped[$field] ?? '')) === '') {
                $resolved[$field] = $placeholder;
                $messages[] = "{$field} was empty and defaulted to {$placeholder}; review it.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function readContacts(array $mapped, array &$resolved, array &$discarded, array &$messages): void
    {
        $phones = $this->contacts->phones((string) ($mapped['phone'] ?? ''));
        // A review edit replays this recognizer on values already split: the
        // numbers split off the first time must survive.
        $extras = array_values(array_unique([...(array) ($mapped['extra_phones'] ?? []), ...$phones['extra_phones']]));

        $resolved['phone'] = $phones['phone'];
        $resolved['extra_phones'] = array_values(array_diff($extras, [$phones['phone']]));

        foreach ($phones['rejected'] as $phone) {
            $this->reject('phone', $phone, 'is not a valid phone number', $discarded, $messages);
        }

        $email = trim((string) ($mapped['email'] ?? ''));
        $resolved['email'] = $this->contacts->email($email);

        if ($email !== '' && $resolved['email'] === null) {
            $this->reject('email', $email, 'is not a valid email', $discarded, $messages);
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function readCallback(array $mapped, array &$resolved, array &$discarded, array &$messages): void
    {
        $raw = trim((string) ($mapped['next_callback_date'] ?? ''));
        $resolved['next_callback_at'] = null;

        if ($raw === '') {
            return;
        }

        $date = $this->dates->parse($raw);

        if ($date === null) {
            $this->reject('next_callback_date', $raw, 'is not a readable date', $discarded, $messages);

            return;
        }

        $resolved['next_callback_at'] = $date
            ->setTimeFromTimeString((string) config('imports.request_management.callback_time'))
            ->shiftTimezone((string) config('app.timezone'))
            ->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function readOperator(array $mapped, array &$resolved, array &$discarded, array &$messages): void
    {
        $name = trim((string) ($mapped['operator_name'] ?? ''));
        $resolved['operator_id'] = $name === '' ? null : $this->operators->resolve($name);

        if ($name !== '' && $resolved['operator_id'] === null) {
            $this->reject('operator_name', $name, 'does not match exactly one active user', $discarded, $messages);
        }
    }

    /**
     * The Segnalatore is a Referent: exactly one existing one is linked. None
     * (referents are never created by the import) or several (ambiguous) leave
     * the field empty and the value in the notes — a wrong person is worse than none.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function readReporter(array $mapped, array &$resolved, array &$discarded, array &$messages): void
    {
        $name = trim((string) ($mapped['reporter_name'] ?? ''));
        $ids = $name === '' ? [] : $this->lookups->referentIds($name);

        $resolved['reporter_id'] = count($ids) === 1 ? $ids[0] : null;

        if ($name !== '' && count($ids) !== 1) {
            $this->reject('reporter_name', $name, $ids === [] ? 'matches no referent' : 'matches more than one referent', $discarded, $messages);
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function readProduct(array $mapped, int $categoryId, array &$resolved, array &$discarded, array &$messages): void
    {
        $text = trim((string) ($mapped['product_name'] ?? ''));
        $productId = $text === '' || $categoryId === 0 ? null : $this->products->resolve($categoryId, $text);
        $resolved['product_ids'] = $productId === null ? [] : [$productId];

        if ($text !== '' && $productId === null) {
            $this->reject('product_name', $text, 'matches no product of the chosen category', $discarded, $messages);
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $resolved
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function readAttributes(array $mapped, int $categoryId, array &$resolved, array &$discarded, array &$messages): void
    {
        $attributes = $this->attributes->resolve($categoryId, $mapped);
        $resolved['attribute_values'] = $attributes['values'];

        foreach ($attributes['rejected'] as $rejected) {
            $discarded[] = $rejected;
            $messages[] = "\"{$rejected['label']}\": the value \"{$rejected['value']}\" is not valid and was kept in the notes.";
        }
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $discarded
     * @param  array<int, string>  $messages
     */
    private function reject(string $field, string $value, string $reason, array &$discarded, array &$messages): void
    {
        $label = (string) config("imports.request_management.note_labels.{$field}", $field);

        $discarded[] = ['label' => $label, 'value' => $value];
        $messages[] = "{$label}: \"{$value}\" {$reason}; it was kept in the notes.";
    }
}
