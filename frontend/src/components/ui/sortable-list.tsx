import * as React from "react"
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core"
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { GripVertical } from "lucide-react"

import { cn } from "@/lib/utils"

interface SortableListItem {
  id: string
}

interface SortableListProps<T extends SortableListItem> {
  /** Full ordered list, including any pinned items. */
  items: T[]
  /** Renders the row content; the drag handle (or its placeholder) is added around it. */
  renderItem: (item: T) => React.ReactNode
  /**
   * Fires after a reorder with the full list of ids in their new visual
   * order (pinned ids included, unmoved). The caller owns persistence —
   * pass back a reverted `items` prop to undo an optimistic move.
   */
  onReorder: (orderedIds: string[]) => void
  /**
   * Marks an item as fixed: no handle, not draggable, not a drop target.
   * Pinned items must form a prefix and/or suffix of `items` (e.g. a fixed
   * first/last row) — one in the middle of non-pinned items is not supported.
   */
  isPinned?: (item: T) => boolean
  /** Accessible name for every drag handle button (translated by the caller). */
  dragHandleLabel: string
  className?: string
  /** Overrides the pinned row's default muted background (e.g. `bg-card` to match the sortable rows). */
  pinnedRowClassName?: string
  /** Per-item row classes on top of the defaults (e.g. a tint keyed by the item's own type). */
  itemClassName?: (item: T) => string | undefined
}

/** Splits `items` into a leading pinned run, the reorderable middle, and a trailing pinned run. */
function splitPinnedEdges<T extends SortableListItem>(
  items: T[],
  isPinned?: (item: T) => boolean
): { leading: T[]; sortable: T[]; trailing: T[] } {
  if (!isPinned) {
    return { leading: [], sortable: items, trailing: [] }
  }

  let start = 0
  while (start < items.length && isPinned(items[start])) {
    start++
  }

  let end = items.length
  while (end > start && isPinned(items[end - 1])) {
    end--
  }

  return {
    leading: items.slice(0, start),
    sortable: items.slice(start, end),
    trailing: items.slice(end),
  }
}

interface RowProps<T extends SortableListItem> {
  item: T
  renderItem: (item: T) => React.ReactNode
}

/** Fixed row: same shape as a draggable row but without a handle, so labels stay aligned. */
function PinnedRow<T extends SortableListItem>({
  item,
  renderItem,
  className,
}: RowProps<T> & { className?: string }) {
  return (
    <li className={cn("flex items-center gap-2 rounded-md border bg-muted/40 px-2 py-1.5 text-sm", className)}>
      <span className="size-3.5 shrink-0" aria-hidden="true" />
      <div className="min-w-0 flex-1">{renderItem(item)}</div>
    </li>
  )
}

/** Draggable row: mouse/touch via the handle's pointer listeners, keyboard via Space/Enter + arrows. */
function SortableRow<T extends SortableListItem>({
  item,
  renderItem,
  dragHandleLabel,
  className,
}: RowProps<T> & { dragHandleLabel: string; className?: string }) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } =
    useSortable({ id: item.id })

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <li
      ref={setNodeRef}
      style={style}
      className={cn(
        "flex items-center gap-2 rounded-md border bg-card px-2 py-1.5 text-sm",
        className,
        isDragging && "z-10 opacity-70 shadow-md"
      )}
    >
      <button
        type="button"
        ref={setActivatorNodeRef}
        aria-label={dragHandleLabel}
        className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing"
        {...attributes}
        {...listeners}
      >
        <GripVertical className="size-3.5" aria-hidden="true" />
      </button>
      <div className="min-w-0 flex-1">{renderItem(item)}</div>
    </li>
  )
}

/**
 * Compact vertical reorder list on top of @dnd-kit/sortable. Supports pinned
 * edge rows (see `isPinned`) that render without a handle and sit outside
 * the drag context entirely, so the drag gesture can never cross them.
 * Reorder works from the mouse/touch handle and from the keyboard (focus the
 * handle, Space/Enter to pick up, arrow keys to move, Space/Enter to drop).
 */
function SortableList<T extends SortableListItem>({
  items,
  renderItem,
  onReorder,
  isPinned,
  dragHandleLabel,
  className,
  pinnedRowClassName,
  itemClassName,
}: SortableListProps<T>) {
  // Mirrors `items` for optimistic in-place reordering, and re-syncs
  // whenever the caller passes a new `items` reference (e.g. to revert an
  // optimistic move after the server rejects it). Adjusted during render
  // rather than in an effect, per React's "adjusting state on prop change"
  // recipe — avoids an extra render pass.
  const [orderedItems, setOrderedItems] = React.useState(items)
  const [syncedItems, setSyncedItems] = React.useState(items)
  if (items !== syncedItems) {
    setSyncedItems(items)
    setOrderedItems(items)
  }

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  const { leading, sortable, trailing } = splitPinnedEdges(orderedItems, isPinned)
  const sortableIds = sortable.map((item) => item.id)

  function handleDragEnd(event: DragEndEvent) {
    // Step 1: ignore no-op drags (dropped outside or back on itself)
    const { active, over } = event
    if (!over || active.id === over.id) {
      return
    }

    const oldIndex = sortableIds.indexOf(String(active.id))
    const newIndex = sortableIds.indexOf(String(over.id))
    if (oldIndex === -1 || newIndex === -1) {
      return
    }

    // Step 2: reorder the non-pinned segment, re-assemble the full list, notify the caller
    const reorderedSortable = arrayMove(sortable, oldIndex, newIndex)
    const nextItems = [...leading, ...reorderedSortable, ...trailing]
    setOrderedItems(nextItems)
    onReorder(nextItems.map((item) => item.id))
  }

  return (
    <ul className={cn("flex flex-col gap-1.5", className)}>
      {leading.map((item) => (
        <PinnedRow
          key={item.id}
          item={item}
          renderItem={renderItem}
          className={cn(pinnedRowClassName, itemClassName?.(item))}
        />
      ))}
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={sortableIds} strategy={verticalListSortingStrategy}>
          {sortable.map((item) => (
            <SortableRow
              key={item.id}
              item={item}
              renderItem={renderItem}
              dragHandleLabel={dragHandleLabel}
              className={itemClassName?.(item)}
            />
          ))}
        </SortableContext>
      </DndContext>
      {trailing.map((item) => (
        <PinnedRow
          key={item.id}
          item={item}
          renderItem={renderItem}
          className={cn(pinnedRowClassName, itemClassName?.(item))}
        />
      ))}
    </ul>
  )
}

export { SortableList }
export type { SortableListItem, SortableListProps }
