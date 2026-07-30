import { PreviewDividerBlock, PreviewPageBreakBlock, PreviewSpacerBlock } from '@/features/document-layouts/editor/preview/preview-simple-blocks'
import { PreviewImageBlock } from '@/features/document-layouts/editor/preview/preview-image-block'
import { PreviewProductsTableBlock } from '@/features/document-layouts/editor/preview/preview-products-table-block'
import { PreviewTableBlock } from '@/features/document-layouts/editor/preview/preview-table-block'
import { PreviewTextBlock } from '@/features/document-layouts/editor/preview/preview-text-block'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import type { Block } from '@/features/document-layouts/layout-config'

interface PreviewBlockProps {
  block: Block
  imagesById: ReadonlyMap<number, DocumentLayoutImage>
  labelFor: (variable: string) => string
}

/** Dispatches one block to its type-specific preview renderer. `image`+`behind_page` is handled by the caller. */
export function PreviewBlock({ block, imagesById, labelFor }: PreviewBlockProps) {
  switch (block.type) {
    case 'text':
      return <PreviewTextBlock block={block} labelFor={labelFor} />
    case 'image':
      return <PreviewImageBlock block={block} imagesById={imagesById} />
    case 'table':
      return <PreviewTableBlock block={block} labelFor={labelFor} />
    case 'products_table':
      return <PreviewProductsTableBlock block={block} labelFor={labelFor} />
    case 'page_break':
      return <PreviewPageBreakBlock />
    case 'spacer':
      return <PreviewSpacerBlock block={block} />
    case 'divider':
      return <PreviewDividerBlock block={block} />
  }
}
