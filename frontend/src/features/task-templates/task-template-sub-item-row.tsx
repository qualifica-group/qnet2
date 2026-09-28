import { TaskTemplateItemRowContent } from '@/features/task-templates/task-template-items-editor'
import { cn } from '@/lib/utils'
import type {
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
} from '@/features/task-templates/types'

/** One Tailwind class per nesting `depth` (1..`MAX_ITEM_DEPTH`) — a literal lookup so Tailwind's static scan keeps finding them (no runtime-built arbitrary value). */
const SUB_ITEM_INDENT_CLASS: Record<number, string> = { 1: 'ml-4', 2: 'ml-8', 3: 'ml-12' }

export interface TaskTemplateSubItemRowProps {
  row: TaskTemplateItemFormRow
  depth: number
  errors: TaskTemplateItemErrors[string]
  stagedFiles: File[]
  canAddSubtask: boolean
  onUpdateRow: (id: string, patch: TaskTemplateItemRowPatch) => void
  onRemove: (id: string) => void
  onAddSubtask: (id: string) => void
  onAddStagedFiles: (rowId: string, files: File[]) => void
  onRemoveStagedFile: (rowId: string, index: number) => void
  disabled: boolean
}

/**
 * One sub-task row (spec 0172 D-1/D-3): no drag handle (sub-items are not
 * draggable on their own, out of scope) and no "Fase" select (it follows the
 * fase of the root it nests under, `<TaskTemplateStageItemRow>` owns that
 * select). Indented under its parent's position inside the same stage card —
 * `<TaskTemplateStageCard>` renders one of these per non-root
 * `TaskTemplateStageGroupEntry`.
 */
export function TaskTemplateSubItemRow({
  row,
  depth,
  errors,
  stagedFiles,
  canAddSubtask,
  onUpdateRow,
  onRemove,
  onAddSubtask,
  onAddStagedFiles,
  onRemoveStagedFile,
  disabled,
}: TaskTemplateSubItemRowProps) {
  return (
    <li className={cn('flex flex-col gap-2 rounded-md border bg-card p-2', SUB_ITEM_INDENT_CLASS[depth] ?? 'ml-12')}>
      <TaskTemplateItemRowContent
        row={row}
        errors={errors}
        stagedFiles={stagedFiles}
        canAddSubtask={canAddSubtask}
        onUpdateRow={onUpdateRow}
        onRemove={onRemove}
        onAddSubtask={onAddSubtask}
        onAddStagedFiles={onAddStagedFiles}
        onRemoveStagedFile={onRemoveStagedFile}
        disabled={disabled}
      />
    </li>
  )
}
