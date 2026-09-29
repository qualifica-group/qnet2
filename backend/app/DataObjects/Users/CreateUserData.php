<?php

namespace App\DataObjects\Users;

/**
 * Validated payload for creating a user (POST /api/users).
 *
 * Declared DTO (no "magic flying array") so the StoreUserRequest → UserService
 * contract is explicit and the service reads typed properties instead of
 * `$data['name']` / `array_key_exists('roles', $data)` — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `roles` is null when the client did not submit the key at all (leave roles
 * untouched), versus an explicit list (sync exactly those).
 *
 * `password` is null in INVITE mode (spec 0177): the admin chose no password, the
 * UserService seeds an unknown placeholder and the user picks their own via the
 * emailed link.
 *
 * `name` is intentionally absent: the client no longer supplies it; it is derived
 * server-side from the nested personal-data card (ADR 0012,
 * CreatePersonalData::displayName()) and set by the UserService.
 */
final readonly class CreateUserData
{
    /**
     * @param  array<int, string>|null  $roles
     */
    public function __construct(
        public string $email,
        public string $locale,
        public ?string $password,
        public bool $is_active = true,
        public ?array $roles = null,
    ) {}

    /**
     * Build from the validated StoreUserRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            email: (string) $data['email'],
            locale: (string) $data['locale'],
            password: isset($data['password']) ? (string) $data['password'] : null,
            is_active: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            roles: array_key_exists('roles', $data) ? (array) $data['roles'] : null,
        );
    }

    public function hasRoles(): bool
    {
        return $this->roles !== null;
    }

    /**
     * The account attributes for a mass-assignment create (framework array
     * boundary). `name` is NOT included here: it is derived from the personal-data
     * card and merged in by the UserService (ADR 0012).
     *
     * @return array<string, string|bool|null>
     */
    public function attributes(): array
    {
        return [
            'email' => $this->email,
            'locale' => $this->locale,
            'password' => $this->password,
            'is_active' => $this->is_active,
        ];
    }
}
