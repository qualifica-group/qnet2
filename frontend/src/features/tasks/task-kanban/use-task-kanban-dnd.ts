/**
 * Shared drag-and-drop engine for BOTH /tasks Kanban boards (spec 0157 D-2):
 * unlike the commessa's Task board, a card's position INSIDE a column carries
 * no meaning here (no `stage_position` equivalent) — only which column it
 * lands ON matters, so this is a plain column-level drop, not a sortable
 * reorder. One `DndContext` per board; `onDrop` resolves what a landed move
 * actually does (PATCH, "Completa" dialog, uncomplete, …).
 */
import { useState } from 'react'
import {
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import type { TaskKanbanGroup, TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

const COLUMN_DROPPABLE_PREFIX = 'kanban-column-'

export function taskKanbanColumnDroppableId(key: string): string {
  return `${COLUMN_DROPPABLE_PREFIX}${key}`
}

interface UseTaskKanbanDndArgs<Key extends string> {
  groups: TaskKanbanGroup<Key>[]
  /** Called once a card is dropped on a DIFFERENT, droppable column whose origin allows dragging out. */
  onDrop: (row: TaskKanbanRow, targetKey: Key, targetGroup: TaskKanbanGroup<Key>) => void
}

/** Sensors + the single drag lifecycle every Kanban card/column shares. */
export function useTaskKanbanDnd<Key extends string>({ groups, onDrop }: UseTaskKanbanDndArgs<Key>) {
  const [activeRow, setActiveRow] = useState<TaskKanbanRow | null>(null)

  const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor))

  const rowsById = new Map(groups.flatMap((group) => group.rows.map((row) => [String(row.id), row] as const)))
  const groupOfRow = new Map(
    groups.flatMap((group) => group.rows.map((row) => [String(row.id), group] as const)),
  )
  const groupsByKey = new Map(groups.map((group) => [String(group.key), group] as const))

  function handleDragStart(event: DragStartEvent) {
    setActiveRow(rowsById.get(String(event.active.id)) ?? null)
  }

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event
    setActiveRow(null)
    if (!over) {
      return
    }
    const row = rowsById.get(String(active.id))
    const originGroup = groupOfRow.get(String(active.id))
    if (!row || !originGroup || !originGroup.draggable) {
      return
    }
    const targetKey = String(over.id).slice(COLUMN_DROPPABLE_PREFIX.length)
    const targetGroup = groupsByKey.get(targetKey)
    if (!targetGroup || !targetGroup.droppable || targetGroup.key === originGroup.key) {
      return
    }
    onDrop(row, targetGroup.key, targetGroup)
  }

  return { sensors, activeRow, handleDragStart, handleDragEnd }
}
