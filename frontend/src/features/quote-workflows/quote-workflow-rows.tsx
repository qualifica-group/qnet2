import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList } from '@/components/detail/record-panel'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Badge } from '@/components/ui/badge'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import {
  QuoteWorkflowCriteriaField,
  QuoteWorkflowStatusesField,
} from '@/features/quote-workflows/quote-workflow-editors'
import type { QuoteWorkflowFormValues } from '@/features/quote-workflows/use-quote-workflow-form'
import type { QuoteWorkflowDetail } from '@/features/quote-workflows/types'
import { cn } from '@/lib/utils'

interface RowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** Name and active flag, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function QuoteWorkflowGeneralRows({ inline, canEdit }: RowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<QuoteWorkflowFormValues>
        name="name"
        label={t('quoteWorkflows.form.name')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<QuoteWorkflowFormValues>
        name="is_active"
        label={t('quoteWorkflows.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}

interface DetailRowProps extends RowsProps {
  quoteWorkflow: QuoteWorkflowDetail
}

/** The detail's criteria: the persisted field/value pairs until the pencil opens the same editor as creation. */
export function QuoteWorkflowCriteriaRow({ inline, canEdit, quoteWorkflow }: DetailRowProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="criteria"
      label={t('quoteWorkflows.detail.criteriaTitle')}
      layout="block"
      inline={inline}
      canEdit={canEdit}
      editor={<QuoteWorkflowCriteriaField />}
    >
      {quoteWorkflow.criteria.length === 0 ? (
        <DetailEmpty />
      ) : (
        <RecordFieldList>
          {quoteWorkflow.criteria.map((criterion) => (
            <RecordField
              key={criterion.id}
              label={criterion.field_source === 'custom' ? criterion.field_label : t(criterion.field_label)}
            >
              {criterion.value_label}
            </RecordField>
          ))}
        </RecordFieldList>
      )}
    </RecordInlineField>
  )
}

/** The detail's statuses: the persisted set with its groups until the pencil opens the same editor as creation. */
export function QuoteWorkflowStatusesRow({ inline, canEdit, quoteWorkflow }: DetailRowProps) {
  const { t } = useTranslation()

  return (
    <RecordInlineField
      field="statuses"
      label={t('quoteWorkflows.detail.statusesTitle')}
      layout="block"
      inline={inline}
      canEdit={canEdit}
      editor={<QuoteWorkflowStatusesField />}
    >
      {quoteWorkflow.statuses.length === 0 ? (
        <DetailEmpty />
      ) : (
        <ul className="flex flex-col gap-1.5">
          {quoteWorkflow.statuses.map((status) => (
            <li key={status.id} className="flex items-center gap-2 text-sm">
              <span
                className={cn('size-2.5 shrink-0 rounded-full border', swatchClassFor(status.color) ?? 'bg-transparent')}
                aria-hidden="true"
              />
              <span className="flex-1 truncate">{status.name}</span>
              <Badge variant="secondary" className="shrink-0">
                {t(`quoteWorkflows.form.statuses.group.${status.group}`)}
              </Badge>
            </li>
          ))}
        </ul>
      )}
    </RecordInlineField>
  )
}
