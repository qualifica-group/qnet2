import { useMemo, useRef } from 'react'
import { cn } from '@/lib/utils'
import { paginateBodyBlocks } from '@/features/document-layouts/editor/preview/preview-pagination'
import { PreviewSheet } from '@/features/document-layouts/editor/preview/preview-sheet'
import { usePreviewMeasurement } from '@/features/document-layouts/editor/preview/use-preview-measurement'
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
 * padding, variable tokens rendered as readable chips. The body flows over as
 * many sheets as it needs, each repeating the `behind_page` background, the
 * header and the footer like the generated document: the sheets drawn are
 * measured and the body blocks re-split until they fit (whole blocks only —
 * D-8 does not promise pixel fidelity to Word, that is the `.docx` of spec
 * 0070).
 */
export function DocumentLayoutPreview({ config, images, variablesCatalog, className }: DocumentLayoutPreviewProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const labelFor = useMemo(() => buildVariableLabelLookup(variablesCatalog), [variablesCatalog])
  const imagesById = useMemo(() => new Map(images.map((image) => [image.attachment_id, image])), [images])
  const measurement = usePreviewMeasurement(containerRef)

  const pages = paginateBodyBlocks(config.body.blocks, measurement)
  const backgroundImages = config.header.blocks.filter(isBehindPageImage)
  const headerFlowBlocks = config.header.blocks.filter((block) => !isBehindPageImage(block))

  return (
    <div ref={containerRef} className={cn('flex flex-col gap-4', className)}>
      {pages.map((bodyBlocks, index) => (
        <PreviewSheet
          key={index}
          page={config.page}
          backgroundImages={backgroundImages}
          headerBlocks={headerFlowBlocks}
          bodyBlocks={bodyBlocks}
          footerBlocks={config.footer.blocks}
          imagesById={imagesById}
          labelFor={labelFor}
          pageNumber={index + 1}
          totalPages={pages.length}
        />
      ))}
    </div>
  )
}
