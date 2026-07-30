import { useTranslation } from 'react-i18next'
import { SortableList } from '@/components/ui/sortable-list'
import { AddBlockMenu } from '@/features/document-layouts/editor/add-block-menu'
import { DocumentLayoutBlockRow } from '@/features/document-layouts/editor/document-layout-block-row'
import type { BlockSelection } from '@/features/document-layouts/editor/use-document-layout-editor'
import type { DocumentLayoutImage } from '@/features/document-layouts/editor/images/document-layout-images-api'
import { MAX_BLOCKS_PER_ZONE } from '@/features/document-layouts/layout-config-defaults'
import type { Block, BlockType, DocumentLayoutZoneName } from '@/features/document-layouts/layout-config'
import type { DefaultableBlockType } from '@/features/document-layouts/layout-config-defaults'

interface DocumentLayoutZoneProps {
  zone: DocumentLayoutZoneName
  blocks: Block[]
  selectedBlockId: string | null
  errorBlockIds: ReadonlySet<string>
  images: DocumentLayoutImage[]
  disabled: boolean
  onSelect: (selection: BlockSelection | null) => void
  onAdd: (zone: DocumentLayoutZoneName, type: DefaultableBlockType) => void
  onAddImage: (zone: DocumentLayoutZoneName, attachmentId: number) => void
  onRemove: (zone: DocumentLayoutZoneName, blockId: string) => void
  onReorder: (zone: DocumentLayoutZoneName, orderedIds: string[]) => void
}

/**
 * One zone's authoring surface: its block list (drag-reorderable via
 * `SortableList`, the same `@dnd-kit/sortable` primitive already used by
 * `status-reorder`) plus its own "add block" menu. Reordering only ever
 * touches this zone's `blocks` array (AC-120): `onReorder` is scoped to
 * `zone` by the caller (`use-document-layout-editor.ts`), never the whole
 * config.
 */
export function DocumentLayoutZone({
  zone,
  blocks,
  selectedBlockId,
  errorBlockIds,
  images,
  disabled,
  onSelect,
  onAdd,
  onAddImage,
  onRemove,
  onReorder,
}: DocumentLayoutZoneProps) {
  const { t } = useTranslation()

  function handleAdd(type: BlockType) {
    if (type === 'image') {
      if (images.length === 0) {
        return
      }
      onAddImage(zone, images[0].attachment_id)
      return
    }
    onAdd(zone, type)
  }

  return (
    <div className="flex flex-col gap-2 rounded-md border border-border bg-card p-3">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-xs font-semibold text-foreground">{t(`documentLayouts.editor.zones.${zone}`)}</h3>
        <AddBlockMenu
          onAdd={handleAdd}
          imageDisabled={images.length === 0}
          disabled={disabled || blocks.length >= MAX_BLOCKS_PER_ZONE}
        />
      </div>
      {blocks.length === 0 ? (
        <p className="text-xs text-muted-foreground italic">{t('documentLayouts.editor.zoneEmpty')}</p>
      ) : (
        <SortableList
          items={blocks}
          dragHandleLabel={t('documentLayouts.editor.dragHandle')}
          onReorder={(orderedIds) => onReorder(zone, orderedIds)}
          renderItem={(block) => (
            <DocumentLayoutBlockRow
              block={block}
              selected={selectedBlockId === block.id}
              hasError={errorBlockIds.has(block.id)}
              disabled={disabled}
              onSelect={() => onSelect({ zone, blockId: block.id })}
              onRemove={() => onRemove(zone, block.id)}
            />
          )}
        />
      )}
    </div>
  )
}
