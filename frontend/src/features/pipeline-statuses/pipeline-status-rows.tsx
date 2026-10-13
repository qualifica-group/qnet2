import { useTranslation } from 'react-i18next'
import { useFormContext, useWatch } from 'react-hook-form'
import { ColorInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { STATUS_GROUPS, type StatusGroupValue } from '@/features/status-reorder/types'
import type { PipelineStatusFormValues } from '@/features/pipeline-statuses/use-pipeline-status-form'

interface PipelineStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** A system row ("Nuovo"/"Chiuso") has a fixed group: only name and color edit. */
  isSystemRow?: boolean
}

/** The status' rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function PipelineStatusRows({ inline, canEdit, isSystemRow = false }: PipelineStatusRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<PipelineStatusFormValues>()
  const group = useWatch({ control, name: 'group' })
  const groupLabel = t('pipelineStatuses.form.group.label')

  return (
    <>
      <TextInlineRow<PipelineStatusFormValues> name="name" label={t('pipelineStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <ColorInlineRow<PipelineStatusFormValues> name="color" label={t('pipelineStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <RecordInlineField
        field="group"
        label={groupLabel}
        inline={inline}
        canEdit={canEdit && !isSystemRow}
        editor={
          <MetaField control={control} name="group" metaKey="group" label={groupLabel}>
            {({ field, disabled }) => (
              <Select
                value={field.value}
                onValueChange={(next) => field.onChange(next as StatusGroupValue)}
                disabled={disabled}
              >
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {STATUS_GROUPS.map((value) => (
                    <SelectItem key={value} value={value}>
                      {t(`pipelineStatuses.form.group.${value}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </MetaField>
        }
      >
        {t(`pipelineStatuses.form.group.${group}`)}
      </RecordInlineField>
    </>
  )
}
