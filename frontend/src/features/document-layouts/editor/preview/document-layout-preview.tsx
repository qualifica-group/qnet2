import { useMemo } from 'react'
import { cn } from '@/lib/utils'
import { PreviewBlock } from '@/features/document-layouts/editor/preview/preview-block'
import { PreviewImageBlock } from '@/features/document-layouts/editor/preview/preview-image-block'
import { pageSizePx, twipsToPx } from '@/features/document-layouts/editor/preview/preview-scale'
import { buildVariableLabelLookup } from '@/features/document-layouts/editor/preview/variable-chip-utils'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import type { DocumentLayoutConfig, ImageBlock } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

interface DocumentLayoutPreviewProps {
  config: DocumentLayoutConfig
  images: DocumentLayoutImage[]
  variablesCatalog?: DocumentLayoutVariablesCatalog
  className?: string
}

function isBehindPageImage(block: DocumentLayoutConfig['header']['blocks'][number]): block is ImageBlock {
  return block.type === 'image' && block.wrap === 'behind_page'
}

/**
 * Client-side A4 preview of the config (spec 0069 D-8/AC-127): same twips-
 * >px scale as `preview-scale.ts`'s single constant, margins rendered as
 * padding, variable tokens rendered as readable chips. Header/body/footer
 * stack top-to-bottom on one sheet (no multi-page pagination simulation —
 * D-8 explicitly does not promise pixel fidelity to Word, that is the
 * `.docx` preview endpoint of spec 0070). `behind_page` header images
 * (header-only, D-11) are pulled out of the normal flow and rendered as an
 * absolutely positioned full-page background layer.
 *
 * The sheet itself simulates physical paper (`.docx` output is always white
 * regardless of the app's theme, like any print/PDF preview): its
 * background is a deliberate, documented exception to the app surface-token
 * rule (`ui-design.md` §1-bis governs the app's own chrome, not simulated
 * document content).
 */
export function DocumentLayoutPreview({ config, images, variablesCatalog, className }: DocumentLayoutPreviewProps) {
  const { width, height } = pageSizePx(config.page.orientation)
  const labelFor = useMemo(() => buildVariableLabelLookup(variablesCatalog), [variablesCatalog])
  const imagesById = useMemo(() => new Map(images.map((image) => [image.attachment_id, image])), [images])

  const backgroundImages = config.header.blocks.filter(isBehindPageImage)
  const headerFlowBlocks = config.header.blocks.filter((block) => !isBehindPageImage(block))

  const padding = {
    paddingTop: twipsToPx(config.page.margins.top),
    paddingRight: twipsToPx(config.page.margins.right),
    paddingBottom: twipsToPx(config.page.margins.bottom),
    paddingLeft: twipsToPx(config.page.margins.left),
  }

  return (
    <div
      role="img"
      aria-label="A4 preview"
      className={cn('relative mx-auto shrink-0 overflow-hidden border border-border shadow-sm', className)}
      style={{ width, height, backgroundColor: '#FFFFFF' }}
    >
      {backgroundImages.map((block) => (
        <PreviewImageBlock key={block.id} block={block} imagesById={imagesById} />
      ))}
      <div className="relative z-10 flex h-full flex-col gap-2" style={padding}>
        <div className="flex flex-col gap-1">
          {headerFlowBlocks.map((block) => (
            <PreviewBlock key={block.id} block={block} imagesById={imagesById} labelFor={labelFor} />
          ))}
        </div>
        <div className="flex flex-1 flex-col gap-1">
          {config.body.blocks.map((block) => (
            <PreviewBlock key={block.id} block={block} imagesById={imagesById} labelFor={labelFor} />
          ))}
        </div>
        <div className="mt-auto flex flex-col gap-1">
          {config.footer.blocks.map((block) => (
            <PreviewBlock key={block.id} block={block} imagesById={imagesById} labelFor={labelFor} />
          ))}
        </div>
      </div>
    </div>
  )
}
