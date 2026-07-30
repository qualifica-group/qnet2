<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\Quote;
use App\Models\User;

/**
 * The per-generation context threaded through every block renderer: the
 * Quote being rendered (with every relation the resolver needs already
 * eager-loaded by QuoteDocumentGenerator), the acting User (for the D-6 PII
 * mask), and the page's usable width in twips (computed once by DocxRenderer,
 * see RenderingUnits::usableWidthTwips() — the single percent->twips
 * conversion point spec 0070 requires).
 */
final readonly class RenderContext
{
    public function __construct(
        public Quote $quote,
        public User $actor,
        public int $usableWidthTwips,
    ) {}

    public function percentToTwips(int $percent): int
    {
        return RenderingUnits::percentToTwips($percent, $this->usableWidthTwips);
    }
}
