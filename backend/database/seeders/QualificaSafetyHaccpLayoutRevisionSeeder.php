<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * One-shot for the November revision of "PREVENTIVO SIC_HACCP"
 * (`Template preventivo _SIC_HACCP Rev. 19.11.2026.docx`):
 * `php artisan db:seed --class=QualificaSafetyHaccpLayoutRevisionSeeder --force`.
 * Same rules as its parent, on this layout only, so that running it never
 * touches "Layout servizi" and "Preventivo ISO" again: config and description
 * overwritten, name and flags kept, a missing layout skipped.
 */
class QualificaSafetyHaccpLayoutRevisionSeeder extends QualificaQuoteLayoutRevisionSeeder
{
    /** @var array<int, string> */
    public const array REVISED_LAYOUTS = ['safety_haccp'];
}
