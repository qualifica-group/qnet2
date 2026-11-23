import { useFieldArray, type Control } from 'react-hook-form'
import {
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable'
import { buildDefaultItem } from '@/features/document-checklists/document-checklist-form-values'
import { CHECKLIST_MAX_ITEMS, type DocumentChecklistFormValues } from '@/features/document-checklists/document-checklist-schema'

/**
 * Entry list of the checklist form: add/remove, reorder by drag (pointer or
 * keyboard) and by the explicit up/down buttons. The array order is the order
 * sent to the server.
 */
export function useChecklistItems(control: Control<DocumentChecklistFormValues>) {
  const { fields, append, remove, move } = useFieldArray({ control, name: 'items' })
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const addItem = () => append(buildDefaultItem())

  const moveBy = (index: number, offset: -1 | 1) => {
    const target = index + offset
    if (target >= 0 && target < fields.length) {
      move(index, target)
    }
  }

  const handleDragEnd = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) {
      return
    }
    const from = fields.findIndex((field) => field.id === active.id)
    const to = fields.findIndex((field) => field.id === over.id)
    if (from !== -1 && to !== -1) {
      move(from, to)
    }
  }

  return {
    fields,
    sensors,
    canAdd: fields.length < CHECKLIST_MAX_ITEMS,
    addItem,
    removeItem: remove,
    moveUp: (index: number) => moveBy(index, -1),
    moveDown: (index: number) => moveBy(index, 1),
    handleDragEnd,
  }
}
