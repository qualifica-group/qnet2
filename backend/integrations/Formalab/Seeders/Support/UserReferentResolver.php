<?php

declare(strict_types=1);

namespace Integrations\Formalab\Seeders\Support;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use App\Services\ReferentService;
use Closure;
use Illuminate\Support\Str;

/**
 * The referent that stands for a user (spec 0427 D-8): the one linked to it
 * (`user_id`), else the one with the same name (case, accents, punctuation and
 * word order ignored; among namesakes the lowest migrated id), else a new
 * internal "Commerciale" referent with the user's name and email. The new
 * referent is not linked to the user: the link merges their commission
 * identities (spec 0090 D-5), a choice left to the people who use them.
 */
final class UserReferentResolver
{
    public const string REFERENT_TYPE = 'Commerciale';

    /** @var array<string, list<array{id: int, migrated: bool}>>|null */
    private ?array $referentsByName = null;

    /** @var array<int, Referent> */
    private array $resolved = [];

    private int $created = 0;

    public function __construct(private readonly ReferentService $referents) {}

    public function createdCount(): int
    {
        return $this->created;
    }

    /**
     * @param  Closure(string): void  $warn
     */
    public function resolve(User $user, User $actor, Closure $warn): Referent
    {
        return $this->resolved[$user->id] ??= $this->find($user, $warn) ?? $this->create($user, $actor);
    }

    /**
     * @param  Closure(string): void  $warn
     */
    private function find(User $user, Closure $warn): ?Referent
    {
        $linked = Referent::query()->where('user_id', $user->id)->first();

        if ($linked !== null) {
            return $linked;
        }

        $namesakes = $this->referentsByName()[self::nameKey((string) $user->name)] ?? [];

        if ($namesakes === []) {
            return null;
        }

        usort($namesakes, static fn (array $a, array $b): int => [$b['migrated'], $a['id']] <=> [$a['migrated'], $b['id']]);

        if (count($namesakes) > 1) {
            $warn(sprintf('%d referenti di nome "%s": usato il referente %d.', count($namesakes), $user->name, $namesakes[0]['id']));
        }

        return Referent::query()->find($namesakes[0]['id']);
    }

    private function create(User $user, User $actor): Referent
    {
        $user->loadMissing('personalData');
        $card = $user->personalData;

        $referent = $this->referents->create(
            $actor,
            new CreateReferentData(
                referentTypeId: ReferentType::query()->where('name', self::REFERENT_TYPE)->value('id'),
                contactScope: ReferentContactScopeEnum::Internal->value,
                notes: null,
            ),
            new ProfileData(
                card: new CreatePersonalData(
                    type: PersonalDataTypeEnum::Individual,
                    firstName: $card?->first_name ?? (string) $user->name,
                    lastName: $card?->last_name,
                ),
                contacts: $user->email ? [new ContactInput(id: null, data: new CreateContact(type: ContactTypeEnum::Email, value: $user->email, isPrimary: true))] : null,
            ),
        );

        $this->referentsByName[self::nameKey((string) $referent->name)][] = ['id' => $referent->id, 'migrated' => false];
        $this->created++;

        return $referent;
    }

    /**
     * Every referent by normalised name, read once.
     *
     * @return array<string, list<array{id: int, migrated: bool}>>
     */
    private function referentsByName(): array
    {
        if ($this->referentsByName === null) {
            $this->referentsByName = [];

            foreach (Referent::query()->toBase()->get(['id', 'name', 'old_id']) as $row) {
                $this->referentsByName[self::nameKey((string) $row->name)][] = ['id' => (int) $row->id, 'migrated' => $row->old_id !== null];
            }
        }

        return $this->referentsByName;
    }

    private static function nameKey(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($words);

        return implode(' ', $words);
    }
}
