import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import {
  EMPTY_VALUE,
  SUMMARY_LIST_CLASS,
  SummaryRow,
} from '@/components/record-form/record-summary'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { ForSelectItem } from '@/features/for-select/types'
import type { BankDraft } from '@/features/company-sites/types'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

interface CompanySiteFormSummaryProps {
  control: Control<CompanySiteFormValues>
  /** Hydrated company ref: the only source of a NAME for the id the form holds. */
  selectedCompanyItem: ForSelectItem | null
  /** The banks buffered by the form, counted live as they are added/removed. */
  banksDraft: BankDraft[]
}

/**
 * The form's side-column recap, in the same card and the same `label / value`
 * rows as every other record form (`SummaryRow`).
 *
 * Every row is read LIVE from the form — it recaps what is about to be saved,
 * never the persisted snapshot — and is gated by the SAME field permission as
 * the control it recaps: a label alone already tells the actor a hidden field
 * exists. The company renders its name only while the hydrated ref still
 * matches the chosen id; after a change the picker's own trigger already names
 * the new one, and inventing a label here would be a guess.
 */
export function CompanySiteFormSummary({
  control,
  selectedCompanyItem,
  banksDraft,
}: CompanySiteFormSummaryProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const name = useWatch({ control, name: 'name' })
  const companyId = useWatch({ control, name: 'company_id' })

  const companyName =
    companyId !== null && selectedCompanyItem?.id === companyId ? selectedCompanyItem.label : null

  return (
    <FormSection
      icon={Info}
      title={t('companySites.detail.summary.title')}
      description={t('companySites.detail.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        <SummaryRow label={t('companySites.form.name')}>{name || EMPTY_VALUE}</SummaryRow>
        {fieldPermission('company_id').visible ? (
          <SummaryRow label={t('companySites.form.company')}>
            {companyName ?? EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('banks').visible ? (
          <SummaryRow label={t('companySites.form.sections.banks.title')}>
            {banksDraft.length > 0 ? banksDraft.length : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
      </dl>
    </FormSection>
  )
}
