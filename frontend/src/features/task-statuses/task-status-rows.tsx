import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  ColorInlineRow,
  IconInlineRow,
  NumberInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { MetaField } from '@/features/authorization/MetaField'
import { TASK_STATUS_GROUPS, type TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { TaskStatusFormValues } from '@/features/task-statuses/use-task-status-form'

const COMPLETION_PERCENTAGE_MIN = 0
const COMPLETION_PERCENTAGE_MAX = 100

interface TaskStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /**
   * A system row (D-5) only accepts name, color, icon and completion
   * percentage (`SystemStatusGuard::assertUpdatable`): the other rows get no pencil.
   */
  isSystemRow?: boolean
}

interface GroupRowProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The phase of the status: its localized label when closed, a select of the fixed groups when open. */
function GroupInlineRow({ inline, canEdit }: GroupRowProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<TaskStatusFormValues>()
  const group = useWatch({ control, name: 'group' })
  const label = t('taskStatuses.form.group.label')

  return (
    <RecordInlineField
      field="group"
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField control={control} name="group" metaKey="group" label={label}>
          {({ field, disabled }) => (
            <Select
              value={field.value}
              onValueChange={(next) => field.onChange(next as TaskStatusGroupValue)}
              disabled={disabled}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {TASK_STATUS_GROUPS.map((value) => (
                  <SelectItem key={value} value={value}>
                    {t(`taskStatuses.form.group.${value}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      }
    >
      {t(`taskStatuses.form.group.${group}`)}
    </RecordInlineField>
  )
}

/** The status' rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function TaskStatusRows({ inline, canEdit, isSystemRow = false }: TaskStatusRowsProps) {
  const { t } = useTranslation()
  const canEditFree = canEdit && !isSystemRow

  return (
    <>
      <TextInlineRow<TaskStatusFormValues> name="name" label={t('taskStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<TaskStatusFormValues>
        name="description"
        label={t('taskStatuses.form.description')}
        inline={inline}
        canEdit={canEditFree}
        multiline
      />
      <ColorInlineRow<TaskStatusFormValues> name="color" label={t('taskStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<TaskStatusFormValues> name="icon" label={t('taskStatuses.form.icon')} inline={inline} canEdit={canEdit} />
      <GroupInlineRow inline={inline} canEdit={canEditFree} />
      <NumberInlineRow<TaskStatusFormValues>
        name="completion_percentage"
        label={t('taskStatuses.form.completionPercentage')}
        inline={inline}
        canEdit={canEdit}
        min={COMPLETION_PERCENTAGE_MIN}
        max={COMPLETION_PERCENTAGE_MAX}
        step={1}
        suffix="%"
      />
      <SwitchInlineRow<TaskStatusFormValues>
        name="is_active"
        label={t('taskStatuses.form.isActive')}
        inline={inline}
        canEdit={canEditFree}
      />
    </>
  )
}
