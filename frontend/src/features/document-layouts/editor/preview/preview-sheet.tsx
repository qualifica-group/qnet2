import { useTranslation } from 'react-i18next'
import { PreviewBlock } from '@/features/document-layouts/editor/preview/preview-block'
import { PreviewImageBlock } from '@/features/document-layouts/editor/preview/preview-image-block'
import { PreviewPageContext } from '@/features/document-layouts/editor/preview/preview-page-context'
import { pageSizePx, twipsToPx } from '@/features/document-layouts/editor/preview/preview-scale'
import { PREVIEW_BLOCK_ATTRIBUTE, PREVIEW_BODY_ATTRIBUTE } from '@/features/document-layouts/editor/preview/use-preview-measurement'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import type { Block, DocumentLayoutConfig, ImageBlock } from '@/features/document-layouts/layout-config'

interface PreviewSheetProps {
  page: DocumentLayoutConfig['page']
  backgroundImages: ImageBlock[]
  headerBlocks: Block[]
  bodyBlocks: Block[]
  footerBlocks: Block[]
  imagesById: ReadonlyMap<number, DocumentLayoutImage>
  labelFor: (variable: string) => string
  pageNumber: number
  totalPages: number
}

/**
 * One A4 sheet of the preview: the `behind_page` background, then header,
 * this sheet's share of the body and footer inside the margins (rendered as
 * padding). The body area is pinned to the room between header and footer
 * (`min-h-0`), so a block that overflows is clipped by the sheet instead of
 * pushing the footer down — and its height is what pagination measures.
 *
 * The sheet simulates physical paper (`.docx` output is always white
 * regardless of the app's theme): its background is a deliberate exception
 * to the app surface-token rule (`ui-design.md` §1-bis governs the app's own
 * chrome, not simulated document content).
 */
export function PreviewSheet({
  page,
  backgroundImages,
  headerBlocks,
  bodyBlocks,
  footerBlocks,
  imagesById,
  labelFor,
  pageNumber,
  totalPages,
}: PreviewSheetProps) {
  const { t } = useTranslation()
  const { width, height } = pageSizePx(page.orientation)
  const padding = {
    paddingTop: twipsToPx(page.margins.top),
    paddingRight: twipsToPx(page.margins.right),
    paddingBottom: twipsToPx(page.margins.bottom),
    paddingLeft: twipsToPx(page.margins.left),
  }

  return (
    <PreviewPageContext value={{ page: pageNumber, totalPages }}>
      <div
        role="img"
        aria-label={t('documentLayouts.editor.preview.sheet')}
        className="relative mx-auto shrink-0 overflow-hidden border border-border shadow-sm"
        style={{ width, height, backgroundColor: '#FFFFFF' }}
      >
        {backgroundImages.map((block) => (
          <PreviewImageBlock key={block.id} block={block} imagesById={imagesById} />
        ))}
        <div className="relative z-10 flex h-full flex-col gap-2" style={padding}>
          <div className="flex flex-col gap-1">
            {headerBlocks.map((block) => (
              <PreviewBlock key={block.id} block={block} imagesById={imagesById} labelFor={labelFor} />
            ))}
          </div>
          <div {...{ [PREVIEW_BODY_ATTRIBUTE]: '' }} className="flex min-h-0 flex-1 flex-col gap-1">
            {bodyBlocks.map((block) => (
              <div key={block.id} {...{ [PREVIEW_BLOCK_ATTRIBUTE]: '' }} className="flow-root">
                <PreviewBlock block={block} imagesById={imagesById} labelFor={labelFor} />
              </div>
            ))}
          </div>
          <div className="mt-auto flex flex-col gap-1">
            {footerBlocks.map((block) => (
              <PreviewBlock key={block.id} block={block} imagesById={imagesById} labelFor={labelFor} />
            ))}
          </div>
        </div>
      </div>
    </PreviewPageContext>
  )
}
