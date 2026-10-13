import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'
import { Info, Layers } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { RichTextContent } from '@/components/rich-text/rich-text-content'
import {
  TaskTemplateDescriptionField,
  TaskTemplateIsActiveField,
  TaskTemplateNameField,
} from '@/features/task-templates/task-template-fields'
import { TaskTemplateStagesEditor } from '@/features/task-templates/task-template-stages-editor'
import type { TaskTemplateBoard } from '@/features/task-templates/use-task-template-board'
import type { TaskTemplateFormValues } from '@/features/task-templates/use-task-template-form'

interface IdentitySectionProps {
  /** What the closed rows show: the saved template on the detail, the draft on create. */
  values: TaskTemplateFormValues
  form: UseFormReturn<TaskTemplateFormValues>
  inline: InlineEdit
  /** `false` without the template's `update` permission: no row offers a pencil. */
  canEdit: boolean
}

/** "Dettagli": name, description and the active flag, one row each (spec 0227 D-2). */
export function TaskTemplateIdentityRecordSection({ values, form, inline, canEdit }: IdentitySectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('taskTemplates.form.sections.identity.title')} icon={<Info />}>
      <RecordFieldList>
        <RecordInlineField
          field="name"
          label={t('taskTemplates.form.name')}
          inline={inline}
          canEdit={canEdit}
          editor={<TaskTemplateNameField control={control} />}
        >
          {values.name.trim() !== '' ? <span className="break-words">{values.name}</span> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="description"
          label={t('taskTemplates.form.description')}
          inline={inline}
          canEdit={canEdit}
          editor={<TaskTemplateDescriptionField control={control} />}
        >
          {values.description ? <RichTextContent html={values.description} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="is_active"
          label={t('taskTemplates.form.isActive')}
          inline={inline}
          canEdit={canEdit}
          editor={<TaskTemplateIsActiveField control={control} />}
        >
          <span>{values.is_active ? t('common.yes') : t('common.no')}</span>
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface BoardEditorProps {
  board: TaskTemplateBoard
  disabled?: boolean
}

/** The stages/items drag board with its form-level messages: the board row's editor, and the create form's open board. */
export function TaskTemplateBoardEditor({ board, disabled = false }: BoardEditorProps) {
  return (
    <div className="flex min-w-0 flex-col gap-2">
      <TaskTemplateStagesEditor {...board.editorProps} disabled={disabled} />
      {board.itemsError ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {board.itemsError}
        </p>
      ) : null}
      {board.stagesError ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {board.stagesError}
        </p>
      ) : null}
    </div>
  )
}

/** The "Fasi e attivita'" section's frame, shared by the detail (a board row) and the create form (the open board). */
export function TaskTemplateBoardSection({ children }: { children: ReactNode }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('taskTemplates.form.sections.stages.title')} icon={<Layers />} full>
      {children}
    </RecordSection>
  )
}
