import { useTranslation } from 'react-i18next'
import {
  ColorInlineRow,
  IconInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { TaskPriorityFormValues } from '@/features/task-priorities/use-task-priority-form'

interface TaskPriorityRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The priority's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function TaskPriorityRows({ inline, canEdit }: TaskPriorityRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<TaskPriorityFormValues> name="name" label={t('taskPriorities.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<TaskPriorityFormValues>
        name="description"
        label={t('taskPriorities.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<TaskPriorityFormValues> name="color" label={t('taskPriorities.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<TaskPriorityFormValues> name="icon" label={t('taskPriorities.form.icon')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<TaskPriorityFormValues>
        name="is_active"
        label={t('taskPriorities.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<TaskPriorityFormValues>
        name="is_default"
        label={t('taskPriorities.form.isDefault')}
        hint={t('taskPriorities.form.isDefaultHint')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
