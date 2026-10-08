import { useTranslation } from 'react-i18next'
import { ClipboardList } from 'lucide-react'
import type { UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { formatDecimal } from '@/features/products/column-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import {
  OpportunityDateField,
  OpportunityEstimatedValueField,
  OpportunityGeneralNotesField,
  OpportunityNameField,
  OpportunitySuccessProbabilityField,
} from '@/features/opportunities/opportunity-fields'
import type { OpportunityRecordSectionProps } from '@/features/opportunities/opportunity-record'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

function TextValue({ value }: { value: string | null }) {
  return value !== null && value.trim() !== '' ? <>{value}</> : <DetailEmpty />
}

function DateValue({ value }: { value: string | null }) {
  return <>{formatDate(value) || <DetailEmpty />}</>
}

/**
 * "Dettagli" of the opportunity record (spec 0198): the title and the
 * planning estimates (BR-5), every row editing in place on its own control.
 * The KPI strip above keeps showing the same four estimates at a glance.
 */
export function OpportunityDetailsSection({ values, form, inline }: OpportunityRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('opportunities.detail.sections.details')} icon={<ClipboardList />}>
      <RecordFieldList>
        <RecordInlineField
          field="name"
          label={t('opportunities.form.name')}
          inline={inline}
          editor={<OpportunityNameField control={control} />}
        >
          <TextValue value={values.name} />
        </RecordInlineField>
        <RecordInlineField
          field="start_date"
          label={t('opportunities.form.startDate')}
          inline={inline}
          editor={<OpportunityDateField control={control} name="start_date" label={t('opportunities.form.startDate')} />}
        >
          <DateValue value={values.start_date} />
        </RecordInlineField>
        <RecordInlineField
          field="expected_close_date"
          label={t('opportunities.form.expectedCloseDate')}
          inline={inline}
          editor={
            <OpportunityDateField
              control={control}
              name="expected_close_date"
              label={t('opportunities.form.expectedCloseDate')}
            />
          }
        >
          <DateValue value={values.expected_close_date} />
        </RecordInlineField>
        <RecordInlineField
          field="estimated_value"
          label={t('opportunities.form.estimatedValue')}
          inline={inline}
          editor={<OpportunityEstimatedValueField control={control} />}
        >
          {formatDecimal(values.estimated_value) || <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="success_probability"
          label={t('opportunities.form.successProbability')}
          inline={inline}
          editor={<OpportunitySuccessProbabilityField control={control} />}
        >
          {values.success_probability !== null ? (
            <span className="tabular-nums">{values.success_probability}%</span>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface OpportunityGeneralNotesRowProps {
  notes: string | null
  form: UseFormReturn<OpportunityFormValues>
  inline: InlineEdit
  className?: string
}

/**
 * "Note generali" across the record's full width, in the request work panel's
 * callout (`NotesCalloutRow`) — the text the operator reads first.
 */
export function OpportunityGeneralNotesRow({ notes, form, inline, className }: OpportunityGeneralNotesRowProps) {
  const { t } = useTranslation()

  return (
    <NotesCalloutRow
      field="general_notes"
      title={t('opportunities.form.sections.generalNotes.title')}
      notes={notes}
      placeholder={t('opportunities.form.generalNotesPlaceholder')}
      inline={inline}
      editor={<OpportunityGeneralNotesField control={form.control} />}
      className={className}
    />
  )
}
