<?php

namespace App\Imports\RequestManagement;

/**
 * The operator column carries a person's name typed by hand: it resolves to
 * the ONE active user with that name (case, accents and spacing ignored).
 * No user, or more than one, resolves to nothing: guessing who works a request
 * is worse than leaving the choice to the run's default.
 */
final class OperatorNameResolver
{
    public function __construct(private readonly RequestImportLookups $lookups) {}

    public function resolve(string $name): ?int
    {
        $ids = $this->lookups->activeUserIds($name);

        return count($ids) === 1 ? $ids[0] : null;
    }
}
