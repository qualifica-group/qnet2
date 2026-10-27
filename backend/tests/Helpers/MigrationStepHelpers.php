<?php

if (! function_exists('migrationStepsFrom')) {
    /**
     * Number of migrations `migrate:rollback --step` must undo to get back to
     * BEFORE the given migration file (it and every later one), so the tests
     * do not need a hard-coded count bumped by each new migration.
     */
    function migrationStepsFrom(string $anchorFile): int
    {
        $files = collect(glob(database_path('migrations/*.php')))->map(fn (string $path): string => basename($path));

        if (! $files->contains($anchorFile)) {
            throw new InvalidArgumentException("Unknown migration anchor [{$anchorFile}].");
        }

        return $files->filter(fn (string $file): bool => $file >= $anchorFile)->count();
    }
}
