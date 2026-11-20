<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\CustomFields\Table\TableRowTrigger;
use App\Enums\AssignmentTargetEnum;
use App\Models\CustomFieldDefinition;
use App\Models\EmailTemplate;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TableRowNotification;
use App\RequestManagement\ApplicableAttribute;
use App\Support\Notifications\RecordDetails;
use App\Support\Notifications\RecordTeam;
use App\Support\Notifications\TableRowDetails;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Turns "a row of a flexible table field fired its event" into notifications
 * (spec 0390). Called explicitly by every writer of flexible values (no model
 * observers, constraint since spec 0077): the work order and quote attribute
 * writers, ProductService and CustomFieldWriter, each passing the map before
 * and after its write.
 *
 * Silent for system writes (D-5): under AssignmentNotifier::muted() (legacy
 * migration, realign, imports) or with nobody authenticated (jobs, console).
 * Notifications leave only once the write is durable, the author excluded.
 */
final class TableRowNotifier
{
    public function __construct(private readonly TableRowEmailRenderer $renderer) {}

    /**
     * @param  Collection<int, ApplicableAttribute>  $attributes  the set the write was validated against
     * @param  array<string, mixed>  $before  `attribute_values` before the write
     * @param  array<string, mixed>  $after  `attribute_values` after the write
     */
    public function notifyAttributes(Model $record, Collection $attributes, array $before, array $after): void
    {
        $fields = $attributes
            ->filter(fn (ApplicableAttribute $attribute): bool => $attribute->type === 'table')
            ->map(fn (ApplicableAttribute $attribute): array => ['key' => $attribute->code, 'name' => $attribute->name, 'config' => $attribute->config]);

        $this->notify($record, $fields, $before, $after);
    }

    /**
     * @param  Collection<string, CustomFieldDefinition>  $definitions
     * @param  array<string, mixed>  $before  the stored custom field values before the write
     * @param  array<string, mixed>  $after  the stored custom field values after the write
     */
    public function notifyCustomFields(Model $record, Collection $definitions, array $before, array $after): void
    {
        $fields = $definitions
            ->filter(fn (CustomFieldDefinition $definition): bool => $definition->type === 'table')
            ->map(fn (CustomFieldDefinition $definition): array => ['key' => $definition->key, 'name' => (string) $definition->label, 'config' => $definition->config ?? []]);

        $this->notify($record, $fields, $before, $after);
    }

    /**
     * @param  Collection<array-key, array{key: string, name: string, config: array<string, mixed>}>  $fields
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function notify(Model $record, Collection $fields, array $before, array $after): void
    {
        // Step 1: bulk writes and writes with nobody behind them stay silent (D-5)
        $actor = Auth::user();

        if (AssignmentNotifier::isMuted() || ! $actor instanceof User) {
            return;
        }

        // Step 2: the rows that fired, per field
        $events = [];

        foreach ($fields as $field) {
            foreach (TableRowTrigger::firedRows($field['config'], $before[$field['key']] ?? null, $after[$field['key']] ?? null) as $row) {
                $events[] = ['field' => $field, 'row' => $row];
            }
        }

        if ($events === []) {
            return;
        }

        // Step 3: dispatch once the write is durable; the record exists by then
        DB::afterCommit(function () use ($record, $actor, $events): void {
            foreach ($events as $event) {
                $this->dispatch($record, $actor, $event['field'], $event['row']);
            }
        });
    }

    /**
     * @param  array{key: string, name: string, config: array<string, mixed>}  $field
     * @param  array<string, mixed>  $row
     */
    private function dispatch(Model $record, User $actor, array $field, array $row): void
    {
        $notify = $field['config']['notify'];
        $users = $this->recipientUsers($record, $notify, $actor);
        $emails = $this->freeAddresses($notify, $users, $actor);

        if ($users->isEmpty() && $emails === []) {
            return;
        }

        $template = is_int($notify['email_template_id'] ?? null) ? EmailTemplate::query()->find($notify['email_template_id']) : null;
        $trigger = is_string($notify['trigger'] ?? null) ? $notify['trigger'] : null;
        $label = RecordDetails::labelFor($record);

        $notification = new TableRowNotification(
            fieldName: $field['name'],
            triggerLabel: $trigger === null ? null : TableRowDetails::columnLabel($field['config'], $trigger),
            recordLabel: trim($label) === '' ? '#'.$record->getKey() : $label,
            target: $this->target($record),
            recordId: (int) $record->getKey(),
            actorName: $actor->name,
            email: $template === null ? null : $this->renderer->render($template, $record, $actor),
            rowDetails: TableRowDetails::for($field['config'], $row),
            recordDetails: RecordDetails::for($record),
        );

        Notification::send($users, $notification);

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * Active users among the record's team, the chosen users and the holders
     * of the chosen roles, the author excluded.
     *
     * @param  array<string, mixed>  $notify
     * @return EloquentCollection<int, User>
     */
    private function recipientUsers(Model $record, array $notify, User $actor): EloquentCollection
    {
        $ids = [
            ...(($notify['record_team'] ?? false) === true ? RecordTeam::userIds($record) : []),
            ...($notify['user_ids'] ?? []),
        ];
        $roleIds = $notify['role_ids'] ?? [];

        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($actor->getKey())
            ->where(function ($query) use ($ids, $roleIds): void {
                $query->whereKey($ids);

                if ($roleIds !== []) {
                    $query->orWhereHas('roles', fn ($roles) => $roles->whereKey($roleIds));
                }
            })
            ->get();
    }

    /**
     * The free addresses, minus those of a user already notified (or of the author).
     *
     * @param  array<string, mixed>  $notify
     * @param  EloquentCollection<int, User>  $users
     * @return array<int, string>
     */
    private function freeAddresses(array $notify, EloquentCollection $users, User $actor): array
    {
        $known = $users->pluck('email')->push($actor->email)->map(fn (mixed $email): string => mb_strtolower((string) $email))->all();

        return array_values(array_filter(
            $notify['emails'] ?? [],
            fn (mixed $email): bool => is_string($email) && ! in_array(mb_strtolower($email), $known, true),
        ));
    }

    private function target(Model $record): ?AssignmentTargetEnum
    {
        return match (true) {
            $record instanceof Registry => AssignmentTargetEnum::Registry,
            $record instanceof Opportunity => AssignmentTargetEnum::Opportunity,
            $record instanceof Quote => AssignmentTargetEnum::Quote,
            $record instanceof WorkOrder => AssignmentTargetEnum::WorkOrder,
            default => null,
        };
    }
}
