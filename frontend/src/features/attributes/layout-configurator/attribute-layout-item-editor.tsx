import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useTranslation } from 'react-i18next'
import { GripVertical, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import { LAYOUT_ITEM_WIDTHS, type LayoutItem, type LayoutItemWidth } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutItemEditorProps {
  item: LayoutItem
  attribute: EffectiveAttribute | undefined
  disabled: boolean
  onWidthChange: (width: LayoutItemWidth) => void
  onRemove: () => void
}

/**
 * One attribute placed in a row: a sortable chip (drag to reorder within the
 * row or move across rows/sections, AC-009) carrying its own width selector
 * and a remove-to-palette action. A `item.attribute_code` that no longer
 * resolves against the category's effective attributes (stale layout) is
 * skipped, mirroring `AttributeLayoutSection`'s defensive behaviour.
 */
export function AttributeLayoutItemEditor({
  item,
  attribute,
  disabled,
  onWidthChange,
  onRemove,
}: AttributeLayoutItemEditorProps) {
  const { t } = useTranslation('attributeLayout')
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: item.attribute_code,
    disabled,
  })

  if (!attribute) {
    return null
  }

  const Icon = FIELD_TYPE_ICONS[attribute.type]
  const style = { transform: CSS.Transform.toString(transform), transition }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex min-w-0 items-center gap-1.5 rounded-md border bg-card px-2 py-1.5 text-xs',
        isDragging && 'z-10 opacity-70 shadow-md',
      )}
    >
      <button
        type="button"
        ref={setActivatorNodeRef}
        aria-label={t('configurator.paletteDragHandleLabel', { name: attribute.name })}
        className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing disabled:pointer-events-none"
        disabled={disabled}
        {...attributes}
        {...listeners}
      >
        <GripVertical className="size-3" aria-hidden="true" />
      </button>
      <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
      <span className="min-w-0 flex-1 truncate text-foreground">{attribute.name}</span>
      <Select value={item.width} onValueChange={(value) => onWidthChange(value as LayoutItemWidth)} disabled={disabled}>
        <SelectTrigger size="sm" className="h-7 w-28 text-xs" aria-label={t('configurator.itemWidthLabel')}>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {LAYOUT_ITEM_WIDTHS.map((width) => (
            <SelectItem key={width} value={width}>
              {t(`configurator.width.${width}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Button
        type="button"
        variant="ghost"
        size="icon-xs"
        className="shrink-0 text-muted-foreground hover:text-destructive"
        aria-label={t('configurator.removeItemLabel', { name: attribute.name })}
        disabled={disabled}
        onClick={onRemove}
      >
        <X aria-hidden="true" />
      </Button>
    </div>
  )
}
