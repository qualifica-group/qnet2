<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\User;

/**
 * The per-generation context threaded through every block renderer: the
 * DocumentRenderSubject being rendered (already prepared by DocumentGenerator),
 * the acting User (for the D-6 PII
 * mask), and the page's usable width in twips (computed once by DocxRenderer,
 * see RenderingUnits::usableWidthTwips() — the single percent->twips
 * conversion point spec 0070 requires).
 */
final readonly class RenderContext
{
    public function __construct(
        public DocumentRenderSubject $subject,
        public User $actor,
        public int $usableWidthTwips,
    ) {}

    /**
     * Replace every `{category.key}` token of $text with the subject's value.
     */
    public function substitute(string $text): string
    {
        return VariableTokens::replace(
            $text,
            fn (string $category, string $key): string => $this->subject->resolveVariable($category, $key, $this->actor),
        );
    }

    public function percentToTwips(int $percent): int
    {
        return RenderingUnits::percentToTwips($percent, $this->usableWidthTwips);
    }
}
