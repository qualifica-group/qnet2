import { useTranslation } from 'react-i18next'
import {
  ColorInlineRow,
  IconInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { TaskImportanceFormValues } from '@/features/task-importances/use-task-importance-form'

interface TaskImportanceRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The priority's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function TaskImportanceRows({ inline, canEdit }: TaskImportanceRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<TaskImportanceFormValues> name="name" label={t('taskImportances.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<TaskImportanceFormValues>
        name="description"
        label={t('taskImportances.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<TaskImportanceFormValues> name="color" label={t('taskImportances.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<TaskImportanceFormValues> name="icon" label={t('taskImportances.form.icon')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<TaskImportanceFormValues>
        name="is_active"
        label={t('taskImportances.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<TaskImportanceFormValues>
        name="is_default"
        label={t('taskImportances.form.isDefault')}
        hint={t('taskImportances.form.isDefaultHint')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
