import { useTranslation } from 'react-i18next'
import { DocumentsSection } from '@/features/attachments/documents-section'
import { TaskTemplateItemAttachmentStaging } from '@/features/task-templates/task-template-item-attachment-staging'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'

export interface TaskTemplateItemAttachmentsProps {
  /** The row's persisted item id, or `undefined` for a row not yet saved (create/new row, D-9). */
  itemId?: number
  stagedFiles: File[]
  onAddStagedFiles: (files: File[]) => void
  onRemoveStagedFile: (index: number) => void
  disabled?: boolean
}

/**
 * Per-row attachment slot (spec 0124 D-9): a persisted row (`itemId` set)
 * manages its documents directly through the generic `<DocumentsSection>`
 * (`task_template_item` alias, `documents` collection); a new/unsaved row has
 * nothing to upload against yet, so files stay staged in memory until the
 * template is submitted (`useTaskTemplateForm` uploads them positionally
 * after create).
 */
export function TaskTemplateItemAttachments({
  itemId,
  stagedFiles,
  onAddStagedFiles,
  onRemoveStagedFile,
  disabled = false,
}: TaskTemplateItemAttachmentsProps) {
  const { t } = useTranslation()

  if (itemId !== undefined) {
    return (
      <div className="rounded-md border bg-muted/20 p-2">
        <p className="mb-2 text-xs font-medium text-muted-foreground">
          {t('taskTemplates.form.items.attachments')}
        </p>
        <DocumentsSection
          resource={TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS}
          id={itemId}
          canUpload={!disabled}
          canDelete={!disabled}
        />
      </div>
    )
  }

  return (
    <TaskTemplateItemAttachmentStaging
      files={stagedFiles}
      onAdd={onAddStagedFiles}
      onRemove={onRemoveStagedFile}
      disabled={disabled}
    />
  )
}
