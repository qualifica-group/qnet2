import { useDroppable } from '@dnd-kit/core'
import { SortableContext, horizontalListSortingStrategy } from '@dnd-kit/sortable'
import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronUp, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { rowDroppableId } from '@/features/attributes/layout-configurator/layout-configurator-dnd'
import { AttributeLayoutItemEditor } from '@/features/attributes/layout-configurator/attribute-layout-item-editor'
import type { LayoutItemWidth, LayoutRow } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutRowEditorProps {
  row: LayoutRow
  attributesByCode: ReadonlyMap<string, EffectiveAttribute>
  disabled: boolean
  isFirst: boolean
  isLast: boolean
  onWidthChange: (attributeCode: string, width: LayoutItemWidth) => void
  onRemoveItem: (attributeCode: string) => void
  onRemoveRow: () => void
  onMoveUp: () => void
  onMoveDown: () => void
}

/**
 * One manual row break (spec `layout-contract`: "rows[] = interruzioni di
 * riga MANUALI") inside a section: a droppable + sortable-horizontal
 * container for its items (AC-009), plus explicit row-level controls (move/
 * remove — "riordino... righe", the deliverable's own words) rather than a
 * second drag lane, keeping the single `DndContext` scoped to attribute
 * placement only.
 */
export function AttributeLayoutRowEditor({
  row,
  attributesByCode,
  disabled,
  isFirst,
  isLast,
  onWidthChange,
  onRemoveItem,
  onRemoveRow,
  onMoveUp,
  onMoveDown,
}: AttributeLayoutRowEditorProps) {
  const { t } = useTranslation('attributeLayout')
  const { setNodeRef, isOver } = useDroppable({ id: rowDroppableId(row.id), disabled })
  const itemIds = row.items.map((item) => item.attribute_code)

  return (
    <div className="flex items-start gap-1.5">
      <ul
        ref={setNodeRef}
        className={cn(
          'flex min-h-11 flex-1 flex-wrap items-center gap-1.5 rounded-md border border-dashed p-2',
          isOver && 'border-primary bg-primary/5',
        )}
      >
        {row.items.length === 0 ? (
          <li className="text-xs text-muted-foreground italic">{t('configurator.rowEmpty')}</li>
        ) : (
          <SortableContext items={itemIds} strategy={horizontalListSortingStrategy}>
            {row.items.map((item) => (
              <li key={item.attribute_code} className="list-none">
                <AttributeLayoutItemEditor
                  item={item}
                  attribute={attributesByCode.get(item.attribute_code)}
                  disabled={disabled}
                  onWidthChange={(width) => onWidthChange(item.attribute_code, width)}
                  onRemove={() => onRemoveItem(item.attribute_code)}
                />
              </li>
            ))}
          </SortableContext>
        )}
      </ul>
      <div className="flex shrink-0 flex-col">
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          aria-label={t('configurator.moveRowUpLabel')}
          disabled={disabled || isFirst}
          onClick={onMoveUp}
        >
          <ChevronUp aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          aria-label={t('configurator.moveRowDownLabel')}
          disabled={disabled || isLast}
          onClick={onMoveDown}
        >
          <ChevronDown aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="text-muted-foreground hover:text-destructive"
          aria-label={t('configurator.removeRowLabel')}
          disabled={disabled}
          onClick={onRemoveRow}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>
    </div>
  )
}
