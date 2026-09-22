import { useTranslation } from 'react-i18next'
import { GripVertical, Trash2 } from 'lucide-react'
import { useDroppable } from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  TaskTemplateStageItemRow,
  type TaskTemplateStageMoveOption,
} from '@/features/task-templates/task-template-stage-item-row'
import type { TaskTemplateStageGroup } from '@/features/task-templates/task-template-item-stage-grouping'
import { cn } from '@/lib/utils'
import type {
  TaskTemplateItemErrors,
  TaskTemplateItemRowPatch,
} from '@/features/task-templates/types'

interface TaskTemplateStageCardProps {
  group: TaskTemplateStageGroup
  nameError?: string
  errors: TaskTemplateItemErrors
  stagedFilesByRow: Record<string, File[]>
  moveOptions: TaskTemplateStageMoveOption[]
  onRenameStage: (id: string, name: string) => void
  onRemoveStage: (id: string) => void
  onUpdateRow: (id: string, patch: TaskTemplateItemRowPatch) => void
  onRemoveRow: (id: string) => void
  onAddStagedFiles: (rowId: string, files: File[]) => void
  onRemoveStagedFile: (rowId: string, index: number) => void
  onMoveToStage: (rowId: string, targetContainerId: string) => void
  disabled: boolean
}

/** Droppable target for a group with zero rows — a `useSortable` row alone gives nothing to drop ONTO. */
function EmptyDropZone({ containerId, label }: { containerId: string; label: string }) {
  const { setNodeRef, isOver } = useDroppable({ id: `container:${containerId}`, data: { type: 'container', containerId } })
  return (
    <div
      ref={setNodeRef}
      className={cn(
        'rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground',
        isOver && 'border-primary bg-accent/40',
      )}
    >
      {label}
    </div>
  )
}

/**
 * One column of the "Fasi" drag board (spec 0146 D-2): a real stage (name
 * input + remove + drag handle for REORDERING the stage itself, registered
 * with the shared `DndContext` as `data.type === 'stage'`) or the pinned
 * "Senza fase" pseudo-card (no handle, no rename, no remove — its rows are
 * simply whatever no stage claims). Its own item rows live in a NESTED
 * `<SortableContext>` inside the SAME outer `DndContext`
 * (`<TaskTemplateStagesEditor>` owns it) — the nesting is what lets a row
 * drag across cards, not just within one.
 */
export function TaskTemplateStageCard({
  group,
  nameError,
  errors,
  stagedFilesByRow,
  moveOptions,
  onRenameStage,
  onRemoveStage,
  onUpdateRow,
  onRemoveRow,
  onAddStagedFiles,
  onRemoveStagedFile,
  onMoveToStage,
  disabled,
}: TaskTemplateStageCardProps) {
  const { t } = useTranslation()
  const isUnassigned = group.stage === null
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({
    id: group.containerId,
    data: { type: 'stage' },
    disabled: disabled || isUnassigned,
  })

  const style = isUnassigned ? undefined : { transform: CSS.Transform.toString(transform), transition }

  return (
    <div
      ref={isUnassigned ? undefined : setNodeRef}
      style={style}
      className={cn(
        'flex flex-col gap-2 rounded-lg border bg-surface p-3',
        isDragging && 'z-10 opacity-70 shadow-md',
      )}
    >
      <div className="flex items-center gap-2">
        {isUnassigned ? (
          <span className="size-3.5 shrink-0" aria-hidden="true" />
        ) : (
          <button
            type="button"
            ref={setActivatorNodeRef}
            aria-label={t('taskTemplates.form.stages.dragHandleLabel')}
            disabled={disabled}
            className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing disabled:pointer-events-none disabled:opacity-50"
            {...attributes}
            {...listeners}
          >
            <GripVertical className="size-3.5" aria-hidden="true" />
          </button>
        )}

        {isUnassigned ? (
          <p className="flex-1 text-sm font-medium text-muted-foreground">{t('taskTemplates.form.stages.noStage')}</p>
        ) : (
          <div className="min-w-0 flex-1">
            <Input
              aria-label={t('taskTemplates.form.stages.namePlaceholder')}
              placeholder={t('taskTemplates.form.stages.namePlaceholder')}
              value={group.stage?.name ?? ''}
              disabled={disabled}
              aria-invalid={!!nameError}
              onChange={(event) => onRenameStage(group.containerId, event.target.value)}
            />
            {nameError ? (
              <span role="alert" className="mt-1 block text-xs font-medium text-destructive">
                {nameError}
              </span>
            ) : null}
          </div>
        )}

        {isUnassigned ? null : (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            className="shrink-0 text-muted-foreground hover:text-destructive"
            aria-label={t('taskTemplates.form.stages.remove')}
            disabled={disabled}
            onClick={() => onRemoveStage(group.containerId)}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        )}
      </div>

      <SortableContext items={group.rows.map((row) => row.id)} strategy={verticalListSortingStrategy}>
        {group.rows.length === 0 ? (
          <EmptyDropZone containerId={group.containerId} label={t('taskTemplates.form.stages.empty')} />
        ) : (
          <ul className="flex flex-col gap-2">
            {group.rows.map((row) => (
              <TaskTemplateStageItemRow
                key={row.id}
                row={row}
                containerId={group.containerId}
                errors={errors[row.id] ?? {}}
                stagedFiles={stagedFilesByRow[row.id] ?? []}
                moveOptions={moveOptions}
                onUpdateRow={onUpdateRow}
                onRemove={onRemoveRow}
                onAddStagedFiles={onAddStagedFiles}
                onRemoveStagedFile={onRemoveStagedFile}
                onMoveToStage={onMoveToStage}
                disabled={disabled}
              />
            ))}
          </ul>
        )}
      </SortableContext>
    </div>
  )
}
