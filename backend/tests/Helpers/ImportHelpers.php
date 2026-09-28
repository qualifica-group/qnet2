<?php

declare(strict_types=1);

if (! function_exists('leadsWizardDetectedColumns')) {
    /**
     * @return array<int, array{name: string, index: int, duplicate: bool}>
     */
    function leadsWizardDetectedColumns(): array
    {
        return [
            ['name' => 'Email', 'index' => 0, 'duplicate' => false],
            ['name' => 'Nome', 'index' => 1, 'duplicate' => false],
            ['name' => 'Cognome', 'index' => 2, 'duplicate' => false],
            ['name' => 'Note Extra', 'index' => 3, 'duplicate' => false],
        ];
    }
}
