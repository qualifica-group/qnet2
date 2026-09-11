<?php

// Agnosticism (spec 0052, D-9, AC-021): the notes CORE component must never
// reference the host module directly — the only place `Opportunity`/
// `RequestManagement*`/the `request-management` slug may appear is
// config/notes.php. Spec 0117 adds a SECOND host (the Task) and with it the
// real test of the rule: one host could be a coincidence, two hosts prove
// the core is not quietly written around either of them.

if (! function_exists('phpFilesUnder')) {
    /**
     * @return array<int, string>
     */
    function phpFilesUnder(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

it('the notes core never references a host module outside config/notes.php (AC-021, spec 0117 AC-018)', function () {
    $files = array_merge(
        [app_path('Models/Note.php')],
        phpFilesUnder(app_path('Notes')),
        phpFilesUnder(app_path('Http/Controllers/Notes')),
        glob(app_path('Http/Resources/Note*.php')) ?: [],
    );

    expect($files)->not->toBeEmpty();

    $needles = [
        'Opportunity', 'RequestManagement', 'request-management',
        'Task', 'TaskNotable', 'App\\Services\\Tasks',
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        foreach ($needles as $needle) {
            expect(str_contains($contents, $needle))
                ->toBeFalse("{$file} references \"{$needle}\" — the host module must only appear in config/notes.php.");
        }
    }
});
