<?php

declare(strict_types=1);

namespace App\Support\Notifications;

/**
 * The label of the button a notification email shows (spec 0381, D-2):
 * "Apri anagrafica", "Apri commessa", ...
 *
 * Derived from the DESTINATION path and not from the record type: the same
 * note opens the Offerta in Gestione Richieste for one recipient and the
 * Opportunity for another, and the button must name where it actually lands.
 */
final class ModuleActionLabel
{
    /**
     * First path segment of every SPA module a notification links to; each
     * has its `notifications.actions.<segment>` label.
     *
     * @var array<int, string>
     */
    private const array MODULES = [
        'registries',
        'referents',
        'opportunities',
        'quotes',
        'request-management',
        'enrollee-management',
        'work-orders',
        'tasks',
        'purchase-requests',
        'proforma-requests',
        'absences',
        'field-change-requests',
    ];

    private const string FALLBACK = 'default';

    /**
     * @param  string  $path  an internal SPA path, query string allowed
     * @return string the i18n key, translated by the caller in the
     *                recipient's locale
     */
    public static function for(string $path): string
    {
        $segment = explode('/', ltrim((string) parse_url($path, PHP_URL_PATH), '/'))[0];

        return 'notifications.actions.'.(in_array($segment, self::MODULES, true) ? $segment : self::FALLBACK);
    }
}
