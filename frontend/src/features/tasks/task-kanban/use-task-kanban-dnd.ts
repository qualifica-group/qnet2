/**
 * Shared drag-and-drop engine for BOTH /tasks Kanban boards (spec 0157 D-2):
 * unlike the commessa's Task board, a card's position INSIDE a column carries
 * no meaning here (no `stage_position` equivalent) — only which column it
 * lands ON matters, so this is a plain column-level drop, not a sortable
 * reorder. One `DndContext` per board; `onDrop` resolves what a landed move
 * actually does (PATCH, "Completa" dialog, uncomplete, …).
 *
 * Spec 0164 D-1: since a column no longer holds its rows client-side (each
 * fetches its own block), the dragged row and its ORIGIN column key travel
 * on the draggable's own `data` (set by `TaskKanbanCard`) instead of being
 * looked up in a `groups[].rows` list — `groups` here is metadata only.
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

/** `data` a draggable card carries (spec 0164 D-1), read back on drag start/end. */
export interface TaskKanbanDragData {
  row: TaskKanbanRow
  groupKey: string
}

function dragDataOf(item: { data: { current?: unknown } }): TaskKanbanDragData | null {
  const data = item.data.current as Partial<TaskKanbanDragData> | undefined
  return data?.row && data.groupKey !== undefined ? (data as TaskKanbanDragData) : null
}

interface UseTaskKanbanDndArgs<Key extends string> {
  groups: TaskKanbanGroup<Key>[]
  /** Called once a card is dropped on a DIFFERENT, droppable column whose origin allows dragging out. */
  onDrop: (row: TaskKanbanRow, originKey: Key, targetKey: Key, targetGroup: TaskKanbanGroup<Key>) => void
}

/** Sensors + the single drag lifecycle every Kanban card/column shares. */
export function useTaskKanbanDnd<Key extends string>({ groups, onDrop }: UseTaskKanbanDndArgs<Key>) {
  const [activeRow, setActiveRow] = useState<TaskKanbanRow | null>(null)

  const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor))

  const groupsByKey = new Map(groups.map((group) => [String(group.key), group] as const))

  function handleDragStart(event: DragStartEvent) {
    setActiveRow(dragDataOf(event.active)?.row ?? null)
  }

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event
    setActiveRow(null)
    if (!over) {
      return
    }
    const data = dragDataOf(active)
    if (!data) {
      return
    }
    const originGroup = groupsByKey.get(data.groupKey)
    if (!originGroup || !originGroup.draggable) {
      return
    }
    const targetKey = String(over.id).slice(COLUMN_DROPPABLE_PREFIX.length)
    const targetGroup = groupsByKey.get(targetKey)
    if (!targetGroup || !targetGroup.droppable || targetGroup.key === originGroup.key) {
      return
    }
    onDrop(data.row, originGroup.key, targetGroup.key, targetGroup)
  }

  return { sensors, activeRow, handleDragStart, handleDragEnd }
}
