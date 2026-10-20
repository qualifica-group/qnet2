<?php

namespace App\Enums;

/**
 * Standing of a person for a skill required by their job roles (spec 0286
 * D-6); derived, never stored.
 */
enum SkillCoverageStatus: string
{
    case Covered = 'covered';
    case Below = 'below';
    case Missing = 'missing';
}
