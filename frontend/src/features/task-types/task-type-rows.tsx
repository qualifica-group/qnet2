import { useTranslation } from 'react-i18next'
import {
  ColorInlineRow,
  IconInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { TaskTypeFormValues } from '@/features/task-types/use-task-type-form'

interface TaskTypeRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The priority's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function TaskTypeRows({ inline, canEdit }: TaskTypeRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<TaskTypeFormValues> name="name" label={t('taskTypes.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<TaskTypeFormValues>
        name="description"
        label={t('taskTypes.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<TaskTypeFormValues> name="color" label={t('taskTypes.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<TaskTypeFormValues> name="icon" label={t('taskTypes.form.icon')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<TaskTypeFormValues>
        name="is_active"
        label={t('taskTypes.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<TaskTypeFormValues>
        name="is_default"
        label={t('taskTypes.form.isDefault')}
        hint={t('taskTypes.form.isDefaultHint')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<TaskTypeFormValues>
        name="requires_time_entry"
        label={t('taskTypes.form.requiresTimeEntry')}
        hint={t('taskTypes.form.requiresTimeEntryHint')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
