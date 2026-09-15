import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SortableList } from '@/components/ui/sortable-list'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { TaskTemplateItemStatusSelect } from '@/features/task-templates/task-template-item-status-select'
import { TaskTemplateItemAttachments } from '@/features/task-templates/task-template-item-attachments'
import type {
  TaskTemplateItemErrors,
  TaskTemplateItemFormRow,
  TaskTemplateItemRowPatch,
} from '@/features/task-templates/types'

export interface TaskTemplateItemsEditorProps {
  rows: TaskTemplateItemFormRow[]
  errors: TaskTemplateItemErrors
  stagedFilesByRow: Record<string, File[]>
  onReorder: (orderedIds: string[]) => void
  onAdd: () => void
  onRemove: (id: string) => void
  onUpdateRow: (id: string, patch: TaskTemplateItemRowPatch) => void
  onAddStagedFiles: (rowId: string, files: File[]) => void
  onRemoveStagedFile: (rowId: string, index: number) => void
  disabled?: boolean
}

/**
 * SortableList-based row editor for a template's `items[]` (spec 0124 D-9):
 * add/edit/remove/reorder, no pinned rows (every row is equally reorderable,
 * unlike `WorkflowStatusesEditor`'s system-locked ones). Local state owned by
 * the caller's hook (`useTaskTemplateForm`) — this module's own equivalent of
 * `WorkflowStatusesEditor`.
 */
export function TaskTemplateItemsEditor({
  rows,
  errors,
  stagedFilesByRow,
  onReorder,
  onAdd,
  onRemove,
  onUpdateRow,
  onAddStagedFiles,
  onRemoveStagedFile,
  disabled = false,
}: TaskTemplateItemsEditorProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <SortableList
        items={rows}
        dragHandleLabel={t('taskTemplates.form.items.dragHandleLabel')}
        onReorder={onReorder}
        renderItem={(row) => (
          <TaskTemplateItemRowContent
            row={row}
            errors={errors[row.id] ?? {}}
            stagedFiles={stagedFilesByRow[row.id] ?? []}
            onUpdateRow={onUpdateRow}
            onRemove={onRemove}
            onAddStagedFiles={onAddStagedFiles}
            onRemoveStagedFile={onRemoveStagedFile}
            disabled={disabled}
          />
        )}
      />

      <Button
        type="button"
        variant="outline"
        size="sm"
        className="w-full border-dashed text-muted-foreground hover:text-foreground"
        disabled={disabled}
        onClick={onAdd}
      >
        <Plus aria-hidden="true" />
        {t('taskTemplates.form.items.add')}
      </Button>
    </div>
  )
}

interface TaskTemplateItemRowContentProps {
  row: TaskTemplateItemFormRow
  errors: TaskTemplateItemErrors[string]
  stagedFiles: File[]
  onUpdateRow: TaskTemplateItemsEditorProps['onUpdateRow']
  onRemove: TaskTemplateItemsEditorProps['onRemove']
  onAddStagedFiles: TaskTemplateItemsEditorProps['onAddStagedFiles']
  onRemoveStagedFile: TaskTemplateItemsEditorProps['onRemoveStagedFile']
  disabled: boolean
}

/** One row's fields, wired with the accessible-error triad (aria-invalid + aria-describedby + `role="alert"`, frontend.md §10). */
function TaskTemplateItemRowContent({
  row,
  errors,
  stagedFiles,
  onUpdateRow,
  onRemove,
  onAddStagedFiles,
  onRemoveStagedFile,
  disabled,
}: TaskTemplateItemRowContentProps) {
  const { t } = useTranslation()
  const titleId = `task-template-item-${row.id}-title`
  const titleErrorId = `${titleId}-error`
  const descriptionId = `task-template-item-${row.id}-description`
  const descriptionErrorId = `${descriptionId}-error`
  const estimatedId = `task-template-item-${row.id}-estimated`
  const estimatedErrorId = `${estimatedId}-error`
  const dueOffsetId = `task-template-item-${row.id}-due-offset`
  const dueOffsetErrorId = `${dueOffsetId}-error`
  const statusErrorId = `task-template-item-${row.id}-status-error`

  return (
    <div className="flex flex-1 flex-col gap-2">
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <Input
            id={titleId}
            aria-label={t('taskTemplates.form.items.title')}
            aria-invalid={!!errors.title}
            aria-describedby={errors.title ? titleErrorId : undefined}
            value={row.title}
            disabled={disabled}
            onChange={(event) => onUpdateRow(row.id, { title: event.target.value })}
          />
          {errors.title ? (
            <span id={titleErrorId} role="alert" className="mt-1 block text-xs font-medium text-destructive">
              {errors.title}
            </span>
          ) : null}
        </div>
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="shrink-0 text-muted-foreground hover:text-destructive"
          aria-label={t('taskTemplates.form.items.remove')}
          disabled={disabled}
          onClick={() => onRemove(row.id)}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>

      <div>
        <Label htmlFor={descriptionId} className="sr-only">
          {t('taskTemplates.form.items.description')}
        </Label>
        <RichTextEditor
          id={descriptionId}
          minHeight="compact"
          placeholder={t('taskTemplates.form.items.descriptionPlaceholder')}
          value={row.description}
          disabled={disabled}
          aria-invalid={!!errors.description}
          aria-describedby={errors.description ? descriptionErrorId : undefined}
          onChange={(html) => onUpdateRow(row.id, { description: html })}
        />
        {errors.description ? (
          <span id={descriptionErrorId} role="alert" className="mt-1 block text-xs font-medium text-destructive">
            {errors.description}
          </span>
        ) : null}
      </div>

      <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div>
          <Label htmlFor={estimatedId} className="text-xs font-normal text-muted-foreground">
            {t('taskTemplates.form.items.estimatedMinutes')}
          </Label>
          <Input
            id={estimatedId}
            type="number"
            min={0}
            step={1}
            inputMode="numeric"
            aria-invalid={!!errors.estimated_minutes}
            aria-describedby={errors.estimated_minutes ? estimatedErrorId : undefined}
            value={row.estimated_minutes ?? ''}
            disabled={disabled}
            onChange={(event) =>
              onUpdateRow(row.id, {
                estimated_minutes: event.target.value === '' ? null : Number(event.target.value),
              })
            }
          />
          {errors.estimated_minutes ? (
            <span id={estimatedErrorId} role="alert" className="mt-1 block text-xs font-medium text-destructive">
              {errors.estimated_minutes}
            </span>
          ) : null}
        </div>

        <div>
          <Label htmlFor={dueOffsetId} className="text-xs font-normal text-muted-foreground">
            {t('taskTemplates.form.items.dueOffsetDays')}
          </Label>
          <Input
            id={dueOffsetId}
            type="number"
            min={0}
            max={3650}
            step={1}
            inputMode="numeric"
            aria-invalid={!!errors.due_offset_days}
            aria-describedby={errors.due_offset_days ? dueOffsetErrorId : undefined}
            value={row.due_offset_days}
            disabled={disabled}
            onChange={(event) =>
              onUpdateRow(row.id, { due_offset_days: event.target.value === '' ? 0 : Number(event.target.value) })
            }
          />
          {errors.due_offset_days ? (
            <span id={dueOffsetErrorId} role="alert" className="mt-1 block text-xs font-medium text-destructive">
              {errors.due_offset_days}
            </span>
          ) : null}
        </div>

        <div>
          <Label className="text-xs font-normal text-muted-foreground">
            {t('taskTemplates.form.items.status')}
          </Label>
          <TaskTemplateItemStatusSelect
            value={row.task_status_id}
            onChange={(value) => onUpdateRow(row.id, { task_status_id: value })}
            disabled={disabled}
            aria-invalid={!!errors.task_status_id}
            aria-describedby={errors.task_status_id ? statusErrorId : undefined}
          />
          {errors.task_status_id ? (
            <span id={statusErrorId} role="alert" className="mt-1 block text-xs font-medium text-destructive">
              {errors.task_status_id}
            </span>
          ) : null}
        </div>
      </div>

      <TaskTemplateItemAttachments
        itemId={row.itemId}
        stagedFiles={stagedFiles}
        onAddStagedFiles={(files) => onAddStagedFiles(row.id, files)}
        onRemoveStagedFile={(index) => onRemoveStagedFile(row.id, index)}
        disabled={disabled}
      />
    </div>
  )
}
