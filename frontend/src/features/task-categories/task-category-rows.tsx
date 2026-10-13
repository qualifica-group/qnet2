import { useTranslation } from 'react-i18next'
import {
  ColorInlineRow,
  IconInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { TaskCategoryParentRow } from '@/features/task-categories/task-category-parent-row'
import type { TaskCategoryDetail } from '@/features/task-categories/types'
import type { TaskCategoryFormValues } from '@/features/task-categories/use-task-category-form'

interface TaskCategoryRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** The saved category on a detail; absent on the create draft. */
  taskCategory?: TaskCategoryDetail
}

/** The category's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function TaskCategoryRows({ inline, canEdit, taskCategory }: TaskCategoryRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<TaskCategoryFormValues> name="name" label={t('taskCategories.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<TaskCategoryFormValues>
        name="description"
        label={t('taskCategories.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<TaskCategoryFormValues> name="color" label={t('taskCategories.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<TaskCategoryFormValues> name="icon" label={t('taskCategories.form.icon')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<TaskCategoryFormValues>
        name="is_active"
        label={t('taskCategories.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <TaskCategoryParentRow inline={inline} canEdit={canEdit} taskCategory={taskCategory} />
    </>
  )
}
