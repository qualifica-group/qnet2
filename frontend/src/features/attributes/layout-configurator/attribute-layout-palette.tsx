import { useDraggable, useDroppable } from '@dnd-kit/core'
import { CSS } from '@dnd-kit/utilities'
import { useTranslation } from 'react-i18next'
import { GripVertical } from 'lucide-react'
import { cn } from '@/lib/utils'
import { PALETTE_DROPPABLE_ID } from '@/features/attributes/layout-configurator/layout-configurator-dnd'
import { FIELD_TYPE_ICONS } from '@/features/custom-fields/field-type-icons'
import type { EffectiveAttribute } from '@/features/product-categories/types'

interface AttributeLayoutPaletteProps {
  attributes: EffectiveAttribute[]
  disabled?: boolean
}

/**
 * The configurator's source container (spec 0062 AC-009): every effective
 * attribute not yet placed in a section, draggable into any row. A plain
 * `useDroppable` region (order among unplaced attributes carries no meaning,
 * so items are draggable-only, not sortable amongst themselves).
 *
 * Hosted inside the category edit form's `bg-card` FormSection (ui-design.md
 * §1-bis rung 3, the frontmost rung): a `--muted` tint, not a `--surface`
 * rung, sets it apart from the card without ever reading as a sunken pit
 * below the container it sits on. The drop zone itself stays a plain dashed
 * outline (no extra fill) so the chips (`bg-card`, frontmost) pop back off
 * the tint.
 */
export function AttributeLayoutPalette({ attributes, disabled = false }: AttributeLayoutPaletteProps) {
  const { t } = useTranslation('attributeLayout')
  const { setNodeRef, isOver } = useDroppable({ id: PALETTE_DROPPABLE_ID, disabled })

  return (
    <div className="rounded-lg border bg-muted/40 p-3">
      <h4 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t('configurator.paletteTitle')}
      </h4>
      <p className="mt-0.5 text-xs text-muted-foreground">{t('configurator.paletteDescription')}</p>
      <ul
        ref={setNodeRef}
        className={cn(
          'mt-2 flex min-h-11 flex-wrap gap-1.5 rounded-md border border-dashed p-2',
          isOver && 'border-primary bg-primary/5',
        )}
      >
        {attributes.length === 0 ? (
          <li className="text-xs text-muted-foreground italic">{t('configurator.paletteEmpty')}</li>
        ) : (
          attributes.map((attribute) => (
            <PaletteChip key={attribute.code} attribute={attribute} disabled={disabled} />
          ))
        )}
      </ul>
    </div>
  )
}

function PaletteChip({ attribute, disabled }: { attribute: EffectiveAttribute; disabled: boolean }) {
  const { t } = useTranslation('attributeLayout')
  const { attributes: dragAttributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: attribute.code,
    disabled,
  })
  const Icon = FIELD_TYPE_ICONS[attribute.type]

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Translate.toString(transform) }}
      className={cn(
        'flex items-center gap-1 rounded-md border bg-card py-1 pr-2 pl-1 text-xs',
        isDragging && 'z-10 opacity-70 shadow-md',
      )}
    >
      <button
        type="button"
        aria-label={t('configurator.paletteDragHandleLabel', { name: attribute.name })}
        className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing disabled:pointer-events-none"
        disabled={disabled}
        {...dragAttributes}
        {...listeners}
      >
        <GripVertical className="size-3" aria-hidden="true" />
      </button>
      <Icon className="size-3.5 text-muted-foreground" aria-hidden="true" />
      <span className="text-foreground">{attribute.name}</span>
    </li>
  )
}
