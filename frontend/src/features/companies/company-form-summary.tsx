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
import type { CompanyFormValues } from '@/features/companies/use-company-form'

interface CompanyFormSummaryProps {
  control: Control<CompanyFormValues>
  /** The persisted comune name, the only source of a LABEL for the id the form holds. Null in create mode. */
  persistedCity: string | null
  /** The persisted comune id, so the label above is only trusted while the choice is unchanged. */
  persistedCityId: number | null
}

/**
 * The form's side-column recap, in the same card and the same `label / value`
 * rows as every other record form (`SummaryRow`).
 *
 * Every row is read LIVE from the form — it recaps what is about to be saved,
 * never the persisted snapshot — and is gated by the SAME field permission as
 * the control it recaps: a label alone already tells the actor a hidden field
 * exists.
 */
export function CompanyFormSummary({
  control,
  persistedCity,
  persistedCityId,
}: CompanyFormSummaryProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const denomination = useWatch({ control, name: 'denomination' })
  const vatNumber = useWatch({ control, name: 'vat_number' })
  const line1 = useWatch({ control, name: 'address.line1' })
  const cityId = useWatch({ control, name: 'address.city_id' })

  // Only while the chosen comune is still the persisted one: after a change the
  // picker's own trigger names the new one, and inventing a label would be a guess.
  const cityName = cityId !== null && persistedCityId === cityId ? persistedCity : null

  return (
    <FormSection
      icon={Info}
      title={t('companies.detail.summary.title')}
      description={t('companies.detail.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        {fieldPermission('denomination').visible ? (
          <SummaryRow label={t('companies.form.denomination')}>
            {denomination || EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('vat_number').visible ? (
          <SummaryRow label={t('companies.form.vatNumber')}>{vatNumber || EMPTY_VALUE}</SummaryRow>
        ) : null}
        {fieldPermission('address').visible ? (
          <>
            <SummaryRow label={t('companies.form.line1')}>{line1 || EMPTY_VALUE}</SummaryRow>
            <SummaryRow label={t('companies.detail.city')}>{cityName ?? EMPTY_VALUE}</SummaryRow>
          </>
        ) : null}
      </dl>
    </FormSection>
  )
}
