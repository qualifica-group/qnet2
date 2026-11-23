import { useTranslation } from 'react-i18next'
import { useFormContext } from 'react-hook-form'
import { DndContext, closestCenter } from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { ChecklistItemFields } from '@/features/document-checklists/checklist-item-fields'
import type { DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'
import { useChecklistItems } from '@/features/document-checklists/use-checklist-items'
import { cn } from '@/lib/utils'

interface ChecklistItemRowProps {
  id: string
  index: number
  count: number
  onMoveUp: () => void
  onMoveDown: () => void
  onRemove: () => void
}

const ICON_BUTTON_CLASS = 'size-7 bg-card'

/** One sortable entry: drag handle + explicit up/down (keyboard) + remove, then its fields. */
function ChecklistItemRow({ id, index, count, onMoveUp, onMoveDown, onRemove }: ChecklistItemRowProps) {
  const { t } = useTranslation()
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id,
  })
  const position = index + 1
  const heading = t('documentChecklists.item.heading', { position })
  const label = (action: string) => `${action}: ${heading}`

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn('rounded-lg border bg-card p-3', isDragging && 'z-10 opacity-70 shadow-md')}
    >
      <div className="mb-3 flex items-center gap-1.5">
        <button
          type="button"
          ref={setActivatorNodeRef}
          aria-label={label(t('documentChecklists.actions.dragHandle'))}
          className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="size-4" aria-hidden="true" />
        </button>
        <h4 className="min-w-0 flex-1 truncate text-sm font-semibold">{heading}</h4>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className={ICON_BUTTON_CLASS}
          aria-label={label(t('documentChecklists.actions.moveUp'))}
          disabled={index === 0}
          onClick={onMoveUp}
        >
          <ArrowUp className="size-3.5" aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className={ICON_BUTTON_CLASS}
          aria-label={label(t('documentChecklists.actions.moveDown'))}
          disabled={index === count - 1}
          onClick={onMoveDown}
        >
          <ArrowDown className="size-3.5" aria-hidden="true" />
        </Button>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className={cn(ICON_BUTTON_CLASS, 'text-destructive')}
          aria-label={label(t('documentChecklists.actions.removeItem'))}
          onClick={onRemove}
        >
          <Trash2 className="size-3.5" aria-hidden="true" />
        </Button>
      </div>
      <ChecklistItemFields index={index} />
    </li>
  )
}

/** The "Voci" section body: sortable list of entries and the add button (spec 0403 AC-024). */
export function ChecklistItemsEditor() {
  const { t } = useTranslation()
  const { control, formState } = useFormContext<DocumentChecklistFormValues>()
  const { fields, sensors, canAdd, addItem, removeItem, moveUp, moveDown, handleDragEnd } = useChecklistItems(control)
  const listError = formState.errors.items?.root?.message ?? formState.errors.items?.message

  return (
    <div className="flex flex-col gap-3">
      {fields.length === 0 ? (
        <p className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
          {t('documentChecklists.item.emptyList')}
        </p>
      ) : (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={fields.map((field) => field.id)} strategy={verticalListSortingStrategy}>
            <ol className="flex flex-col gap-3">
              {fields.map((field, index) => (
                <ChecklistItemRow
                  key={field.id}
                  id={field.id}
                  index={index}
                  count={fields.length}
                  onMoveUp={() => moveUp(index)}
                  onMoveDown={() => moveDown(index)}
                  onRemove={() => removeItem(index)}
                />
              ))}
            </ol>
          </SortableContext>
        </DndContext>
      )}
      {listError ? (
        <p role="alert" className="text-sm text-destructive">
          {listError}
        </p>
      ) : null}
      <div>
        <Button type="button" variant="secondary" size="sm" onClick={addItem} disabled={!canAdd}>
          <Plus className="size-3.5" aria-hidden="true" />
          {t('documentChecklists.actions.addItem')}
        </Button>
      </div>
    </div>
  )
}
