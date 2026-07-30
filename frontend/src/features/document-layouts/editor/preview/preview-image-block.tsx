import { useTranslation } from 'react-i18next'
import { ImageOff } from 'lucide-react'
import { pointsToPx } from '@/features/document-layouts/editor/preview/preview-scale'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import type { ImageBlock } from '@/features/document-layouts/layout-config'

const IMAGE_ALIGN_CSS: Record<ImageBlock['align'], string> = {
  left: 'flex-start',
  center: 'center',
  right: 'flex-end',
}

interface PreviewImageBlockProps {
  block: ImageBlock
  imagesById: ReadonlyMap<number, DocumentLayoutImage>
}

/**
 * Renders one `image` block at its configured points-scale width/height
 * (AC-126: "l'anteprima la mostra alla scala corretta"). `behind_page` is
 * positioned absolutely covering the whole page by the caller
 * (`document-layout-preview.tsx`, which filters it out of the normal flow);
 * this component only ever renders the visual `<img>`/placeholder itself.
 */
export function PreviewImageBlock({ block, imagesById }: PreviewImageBlockProps) {
  const { t } = useTranslation()
  const image = imagesById.get(block.attachment_id)
  const width = pointsToPx(block.width)
  const height = block.height !== null ? pointsToPx(block.height) : undefined
  const isBehindPage = block.wrap === 'behind_page'

  const content = image ? (
    <img
      src={image.data_uri}
      alt={image.filename}
      style={{ width, height }}
      className="max-w-full object-contain"
    />
  ) : (
    <div
      style={{ width, height: height ?? width }}
      className="flex flex-col items-center justify-center gap-1 rounded border border-dashed border-border bg-muted/40 text-muted-foreground"
    >
      <ImageOff className="size-4" aria-hidden="true" />
      <span className="text-[9px]">{t('documentLayouts.editor.preview.missingImage')}</span>
    </div>
  )

  if (isBehindPage) {
    return <div className="absolute inset-0 z-0 flex items-start justify-start overflow-hidden">{content}</div>
  }

  return (
    <div style={{ justifyContent: IMAGE_ALIGN_CSS[block.align] }} className="flex">
      {content}
    </div>
  )
}
