<?php

declare(strict_types=1);

namespace App\CustomFields\Table;

use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;

/**
 * Validates the optional `config.notify` of a `table` definition (spec 0390):
 * which row event fires (`trigger`), which email goes out
 * (`email_template_id`) and who receives it (`record_team`, `user_ids`,
 * `role_ids`, `emails`). Errors keyed by the contract's paths.
 */
final class TableRowNotifyConfigValidator
{
    public const int MAX_IDS = 50;

    public const int MAX_EMAILS = 20;

    /** @var array<string, class-string> */
    private const array ID_LISTS = [
        'user_ids' => User::class,
        'role_ids' => Role::class,
    ];

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param  array<int, mixed>  $columns  the definition's raw columns
     * @return array<string, string>
     */
    public function validate(mixed $notify, array $columns, ?string $selectableKey): array
    {
        $this->errors = [];

        if ($notify === null) {
            return [];
        }

        if (! is_array($notify)) {
            return ['config.notify' => 'The notification must be an object or null.'];
        }

        $this->validateTrigger($notify['trigger'] ?? null, $columns, $selectableKey);
        $this->validateTemplate($notify['email_template_id'] ?? null);
        $recipients = $this->validateRecordTeam($notify['record_team'] ?? false) ? 1 : 0;

        foreach (self::ID_LISTS as $key => $model) {
            $recipients += count($this->validateIds($key, $notify[$key] ?? [], $model));
        }

        $recipients += count($this->validateEmails($notify['emails'] ?? []));

        if ($recipients === 0 && ! $this->hasRecipientError()) {
            $this->errors['config.notify'] = 'The notification needs at least one recipient.';
        }

        return $this->errors;
    }

    /**
     * @param  array<int, mixed>  $columns
     */
    private function validateTrigger(mixed $trigger, array $columns, ?string $selectableKey): void
    {
        if ($trigger === null) {
            return;
        }

        $booleanKeys = collect($columns)
            ->filter(fn (mixed $column): bool => is_array($column) && ($column['type'] ?? null) === 'boolean')
            ->pluck('key')
            ->push($selectableKey)
            ->filter()
            ->all();

        if (! is_string($trigger) || ! in_array($trigger, $booleanKeys, true)) {
            $this->errors['config.notify.trigger'] = 'The trigger must be a yes/no column or the row selection.';
        }
    }

    private function validateTemplate(mixed $templateId): void
    {
        if ($templateId === null) {
            return;
        }

        if (! is_int($templateId) || ! EmailTemplate::query()->whereKey($templateId)->exists()) {
            $this->errors['config.notify.email_template_id'] = 'The email template does not exist.';
        }
    }

    private function validateRecordTeam(mixed $recordTeam): bool
    {
        if (! is_bool($recordTeam)) {
            $this->errors['config.notify.record_team'] = 'The record team flag must be a boolean.';

            return false;
        }

        return $recordTeam;
    }

    /**
     * @param  class-string  $model
     * @return array<int, int> the valid ids
     */
    private function validateIds(string $key, mixed $ids, string $model): array
    {
        $valid = is_array($ids) && array_is_list($ids) && count($ids) <= self::MAX_IDS
            && collect($ids)->every(fn (mixed $id): bool => is_int($id) && $id > 0)
            && count($ids) === count(array_unique($ids));

        if (! $valid) {
            $this->errors["config.notify.{$key}"] = 'Expected a list of at most '.self::MAX_IDS.' distinct ids.';

            return [];
        }

        if ($ids !== [] && $model::query()->whereKey($ids)->count() !== count($ids)) {
            $this->errors["config.notify.{$key}"] = 'One or more selected entries do not exist.';

            return [];
        }

        return $ids;
    }

    /**
     * @return array<int, string> the valid addresses
     */
    private function validateEmails(mixed $emails): array
    {
        $valid = is_array($emails) && array_is_list($emails) && count($emails) <= self::MAX_EMAILS
            && collect($emails)->every(fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            && count($emails) === count(array_unique(array_map(fn (string $email): string => mb_strtolower($email), $emails)));

        if (! $valid) {
            $this->errors['config.notify.emails'] = 'Expected a list of at most '.self::MAX_EMAILS.' distinct valid email addresses.';

            return [];
        }

        return $emails;
    }

    private function hasRecipientError(): bool
    {
        return collect(['record_team', 'user_ids', 'role_ids', 'emails'])
            ->contains(fn (string $key): bool => isset($this->errors["config.notify.{$key}"]));
    }
}
