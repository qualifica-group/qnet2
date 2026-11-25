<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Models\EmploymentProfile;

/**
 * Decides which IBANs of the file go on the employment profile (spec 0431,
 * D-10). The first non-empty IBAN of a person wins; an IBAN different from the
 * stored one, or a person without employment profile, is a warning. An IBAN
 * value never appears in a message.
 */
final class PayslipIbanPlanner
{
    /**
     * @param  list<PayslipEntry>  $entries  the error-free rows
     * @return array<int, string> user id => IBAN to store
     */
    public function plan(array $entries, PayslipIssues $issues): array
    {
        $withIban = array_filter($entries, static fn (PayslipEntry $entry): bool => $entry->iban !== null);
        if ($withIban === []) {
            return [];
        }

        $profiles = EmploymentProfile::query()
            ->whereIn('user_id', array_unique(array_map(static fn (PayslipEntry $entry): int => $entry->userId, $withIban)))
            ->get(['id', 'user_id', 'iban'])
            ->keyBy('user_id');

        $updates = [];
        $handled = [];
        foreach ($withIban as $entry) {
            if (isset($handled[$entry->userId])) {
                continue;
            }
            $handled[$entry->userId] = true;

            $profile = $profiles->get($entry->userId);
            if ($profile === null) {
                $issues->warning($entry->number, null, 'iban_no_profile');

                continue;
            }

            $stored = strtoupper((string) preg_replace('/\s+/', '', (string) $profile->iban));
            if ($stored === $entry->iban) {
                continue;
            }
            if ($stored !== '') {
                $issues->warning($entry->number, null, 'iban_changed');
            }
            $updates[$entry->userId] = (string) $entry->iban;
        }

        return $updates;
    }
}
