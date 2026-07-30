import { DividerBlockInspector } from '@/features/document-layouts/editor/inspector/divider-block-inspector'
import { ImageBlockInspector } from '@/features/document-layouts/editor/inspector/image-block-inspector'
import { PageBreakBlockInspector } from '@/features/document-layouts/editor/inspector/page-break-block-inspector'
import { ProductsTableInspector } from '@/features/document-layouts/editor/inspector/products-table/products-table-inspector'
import { SpacerBlockInspector } from '@/features/document-layouts/editor/inspector/spacer-block-inspector'
import { TableBlockInspector } from '@/features/document-layouts/editor/inspector/table-block-inspector'
import { TextBlockInspector } from '@/features/document-layouts/editor/inspector/text-block-inspector'
import type { Block, DocumentLayoutZoneName } from '@/features/document-layouts/layout-config'
import type { DocumentLayoutVariablesCatalog } from '@/features/document-layouts/variables-api'

interface BlockInspectorDispatchProps {
  zone: DocumentLayoutZoneName
  block: Block
  layoutId: number | null
  variablesCatalog?: DocumentLayoutVariablesCatalog
  disabled: boolean
  onChange: (next: Block) => void
  onActivateRunInsert: (insert: (token: string) => void) => void
}

/** Renders the type-specific inspector for `block` (spec 0069's 7 block types). */
export function BlockInspectorDispatch({
  zone,
  block,
  layoutId,
  variablesCatalog,
  disabled,
  onChange,
  onActivateRunInsert,
}: BlockInspectorDispatchProps) {
  switch (block.type) {
    case 'text':
      return <TextBlockInspector block={block} onChange={onChange} onActivate={onActivateRunInsert} disabled={disabled} />
    case 'image':
      return <ImageBlockInspector block={block} zone={zone} layoutId={layoutId} onChange={onChange} disabled={disabled} />
    case 'table':
      return <TableBlockInspector block={block} onChange={onChange} disabled={disabled} />
    case 'products_table':
      return (
        <ProductsTableInspector block={block} variablesCatalog={variablesCatalog} onChange={onChange} disabled={disabled} />
      )
    case 'page_break':
      return <PageBreakBlockInspector />
    case 'spacer':
      return <SpacerBlockInspector block={block} onChange={onChange} disabled={disabled} />
    case 'divider':
      return <DividerBlockInspector block={block} onChange={onChange} disabled={disabled} />
  }
}
