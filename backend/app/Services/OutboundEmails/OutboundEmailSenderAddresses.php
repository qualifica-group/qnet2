<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Enums\ContactTypeEnum;
use App\Models\User;

/**
 * The From addresses a user may send an OutboundEmail from (spec 0361, D-1):
 * their login `users.email` first, then the `email` contacts of their own
 * personal-data card (primary first), case-insensitive duplicates dropped.
 * PEC contacts are excluded. No free address: the card is editable only by
 * whoever may update the user, so nobody can add a colleague's mailbox.
 */
final class OutboundEmailSenderAddresses
{
    /**
     * @return list<string>
     */
    public function for(User $user): array
    {
        $addresses = [];

        foreach ([(string) $user->email, ...$this->contactEmails($user)] as $address) {
            $address = trim($address);

            if ($address !== '') {
                $addresses[mb_strtolower($address)] ??= $address;
            }
        }

        return array_values($addresses);
    }

    public function allows(User $user, string $address): bool
    {
        return in_array($address, $this->for($user), true);
    }

    /**
     * @return list<string>
     */
    private function contactEmails(User $user): array
    {
        $card = $user->personalData()->first();

        if ($card === null) {
            return [];
        }

        return $card->contacts()
            ->where('type', ContactTypeEnum::Email)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->map(fn ($contact): string => (string) $contact->value)
            ->all();
    }
}
