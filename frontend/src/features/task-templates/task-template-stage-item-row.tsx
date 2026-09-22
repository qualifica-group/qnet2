import { useTranslation } from 'react-i18next'
import { GripVertical } from 'lucide-react'
import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { TaskTemplateItemRowContent } from '@/features/task-templates/task-template-items-editor'
import { cn } from '@/lib/utils'
import type {
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
} from '@/features/task-templates/types'

/** One "Fase" (or the "Senza fase" pseudo-group) a row may be moved into via the select below. */
export interface TaskTemplateStageMoveOption {
  containerId: string
  label: string
}

export interface TaskTemplateStageItemRowProps {
  row: TaskTemplateItemFormRow
  containerId: string
  errors: TaskTemplateItemErrors[string]
  stagedFiles: File[]
  moveOptions: TaskTemplateStageMoveOption[]
  onUpdateRow: (id: string, patch: TaskTemplateItemRowPatch) => void
  onRemove: (id: string) => void
  onAddStagedFiles: (rowId: string, files: File[]) => void
  onRemoveStagedFile: (rowId: string, index: number) => void
  /** Moves the row to the end of the target group — the keyboard-accessible equivalent of a cross-fase pointer drop (AC-031). */
  onMoveToStage: (rowId: string, targetContainerId: string) => void
  disabled: boolean
}

/**
 * One item row inside a `<TaskTemplateStageCard>` (spec 0146 D-2): the drag
 * handle registers the row with the board's shared `DndContext` (pointer
 * drag ACROSS fasi, `data.type === 'item'` — see `TaskTemplateStagesEditor`'s
 * `handleDragEnd`), and the "Fase" select next to it is the keyboard-operable
 * equivalent of the same move (dnd-kit's default keyboard coordinate getter
 * only reorders WITHIN one `SortableContext`, never across containers — this
 * select is the accessible escape hatch for that, not a decoration). The row's
 * OWN fields are `<TaskTemplateItemRowContent>`, shared with nothing else.
 */
export function TaskTemplateStageItemRow({
  row,
  containerId,
  errors,
  stagedFiles,
  moveOptions,
  onUpdateRow,
  onRemove,
  onAddStagedFiles,
  onRemoveStagedFile,
  onMoveToStage,
  disabled,
}: TaskTemplateStageItemRowProps) {
  const { t } = useTranslation()
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: row.id,
    data: { type: 'item', containerId },
    disabled,
  })

  const style = { transform: CSS.Transform.toString(transform), transition }

  return (
    <li
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex flex-col gap-2 rounded-md border bg-card p-2',
        isDragging && 'z-10 opacity-70 shadow-md',
      )}
    >
      <div className="flex items-center gap-2">
        <button
          type="button"
          ref={setActivatorNodeRef}
          aria-label={t('taskTemplates.form.items.dragHandleLabel')}
          disabled={disabled}
          className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing disabled:pointer-events-none disabled:opacity-50"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="size-3.5" aria-hidden="true" />
        </button>

        <Select value={containerId} onValueChange={(next) => onMoveToStage(row.id, next)} disabled={disabled}>
          <SelectTrigger size="sm" aria-label={t('taskTemplates.form.sections.stages.title')} className="w-auto min-w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {moveOptions.map((option) => (
              <SelectItem key={option.containerId} value={option.containerId}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <TaskTemplateItemRowContent
        row={row}
        errors={errors}
        stagedFiles={stagedFiles}
        onUpdateRow={onUpdateRow}
        onRemove={onRemove}
        onAddStagedFiles={onAddStagedFiles}
        onRemoveStagedFile={onRemoveStagedFile}
        disabled={disabled}
      />
    </li>
  )
}
