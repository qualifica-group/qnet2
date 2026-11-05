<?php

namespace App\Imports\RequestManagement;

/**
 * `RequestManagementImportDefinition::validateRow()` (spec 0348): a row is
 * worth importing when it says WHO the client is — a first and last name that
 * are not the staging placeholder, a company name — or how to reach them.
 * Everything else a row may get wrong is a warning (RequestRowRecognizer),
 * never an error. $mapped is the value set AFTER the recognizers ran.
 */
final class RequestRowValidator
{
    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, string>
     */
    public function validate(array $mapped): array
    {
        return $this->hasIdentity($mapped) || $this->hasContact($mapped)
            ? []
            : ['A row needs a first name + last name, a company name, or at least one contact (email or phone).'];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function hasIdentity(array $mapped): bool
    {
        $placeholder = (string) config('imports.placeholder');
        $hasName = $this->filled($mapped, 'first_name', $placeholder) && $this->filled($mapped, 'last_name', $placeholder);

        return $hasName || $this->filled($mapped, 'company_name');
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function hasContact(array $mapped): bool
    {
        return $this->filled($mapped, 'email') || $this->filled($mapped, 'phone');
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function filled(array $mapped, string $field, ?string $placeholder = null): bool
    {
        $value = trim((string) ($mapped[$field] ?? ''));

        return $value !== '' && $value !== $placeholder;
    }
}
