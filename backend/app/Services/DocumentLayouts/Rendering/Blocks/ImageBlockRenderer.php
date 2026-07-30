<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Models\Attachment;
use App\Services\DocumentLayouts\Rendering\RenderContext;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Style\Image as ImageStyle;

/**
 * Renders an `image` block (spec 0069 config_schema #2 / spec 0070
 * rendering_contract): `wrap: 'inline'` places it in the text flow;
 * `wrap: 'behind_page'` floats it absolutely anchored to the PAGE, behind
 * the text — the carta intestata case. Width/height are already in POINTS
 * (this library's own `Style\Image` default unit), so the config values go
 * straight into the style array, no conversion.
 *
 * CAVEAT (verified against the installed phpoffice/phpword 1.4 source, not
 * assumed): `addImage()` is written by
 * `Writer\Word2007\Element\Image::writeImage()`, which emits legacy VML
 * (`v:shape`/`w:pict` + a CSS-like `style="width:...pt;height:...pt;..."`
 * attribute), NOT modern DrawingML (`w:drawing`/`wp:extent` in EMU). A
 * floating image's "behind text" state IS real, but it is expressed
 * differently than the literal AC-250/AC-251 wording assumes:
 * `Writer\Word2007\Style\Frame::write()` computes a negative `z-index`
 * (`-2147483647`) for `wrap: behind`/`infront` and, for exactly those two
 * values, DELIBERATELY SKIPS writing a `w10:wrap` element at all (its `$wrap`
 * local is nulled right before the call) — so there is no literal
 * `wrap type="behind"` attribute anywhere in the XML; the negative z-index
 * plus `mso-position-horizontal/vertical-relative:page` (page anchor) and
 * `position:absolute` are the real, verified equivalent. Width/height are
 * NOT emitted as EMU integers either — they stay in points, suffixed `pt`,
 * inside that same style string. AC-251's literal "EMU = points * 12700" is
 * therefore not what this library version produces; RenderingUnits::
 * POINTS_TO_EMU is kept for documentation only. Both deviations are asserted
 * against directly (not glossed over) in BlockRenderingTest.
 *
 * The attachment binary is read from its OWN disk (Storage::disk($disk),
 * never a hardcoded 'local') via `path()` — never a URL, per the rendering
 * contract's "no network access" constraint. A referenced attachment or file
 * missing from disk is skipped rather than thrown (defensive: the config was
 * already validated at layout save time; a file vanishing afterwards must
 * not turn a quote's document generation into a 500).
 */
final class ImageBlockRenderer
{
    private const string WRAP_BEHIND_PAGE = 'behind_page';

    /**
     * @param  array<string, mixed>  $block
     */
    public function render(AbstractContainer $container, array $block, RenderContext $context): void
    {
        $path = $this->resolvePath((int) $block['attachment_id']);

        if ($path === null) {
            return;
        }

        $container->addImage($path, $this->style($block));
    }

    private function resolvePath(int $attachmentId): ?string
    {
        $attachment = Attachment::query()->find($attachmentId);

        if ($attachment === null) {
            return null;
        }

        $disk = Storage::disk($attachment->disk);

        return $disk->exists($attachment->path) ? $disk->path($attachment->path) : null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function style(array $block): array
    {
        $style = [
            'width' => $block['width'],
            'align' => $block['align'],
        ];

        if ($block['height'] !== null) {
            $style['height'] = $block['height'];
        }

        return $block['wrap'] === self::WRAP_BEHIND_PAGE
            ? [...$style, ...$this->behindPageStyle()]
            : [...$style, 'wrappingStyle' => ImageStyle::WRAP_INLINE];
    }

    /**
     * @return array<string, mixed>
     */
    private function behindPageStyle(): array
    {
        return [
            'wrappingStyle' => ImageStyle::WRAP_BEHIND,
            'positioning' => ImageStyle::POSITION_ABSOLUTE,
            'posHorizontalRel' => ImageStyle::POSITION_RELATIVE_TO_PAGE,
            'posVerticalRel' => ImageStyle::POSITION_RELATIVE_TO_PAGE,
            'marginTop' => 0,
            'marginLeft' => 0,
        ];
    }
}
