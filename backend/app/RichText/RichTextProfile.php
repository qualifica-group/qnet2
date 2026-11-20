<?php

declare(strict_types=1);

namespace App\RichText;

/**
 * Which allow-list RichTextSanitizer applies. `Standard` is the historical one
 * (notes, tasks, templates, email); `Page` adds the block vocabulary of the work
 * order page (spec 0380, D-4) and is never used elsewhere.
 */
enum RichTextProfile
{
    case Standard;
    case Page;
}
