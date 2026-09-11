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
import type { GeoReference } from '@/features/operational-sites/types'
import type { OperationalSiteFormValues } from '@/features/operational-sites/use-operational-site-form'

interface OperationalSiteFormSummaryProps {
  control: Control<OperationalSiteFormValues>
  /** The persisted Comune, the only source of a NAME for the id the form holds. Null in create mode. */
  persistedCity: GeoReference | null
}

/**
 * The form's side-column recap, in the same card and the same `label / value`
 * rows as every other record form (`SummaryRow`).
 *
 * Every row is read LIVE from the form — it recaps what is about to be saved,
 * never the persisted snapshot — and is gated by the SAME field permission as
 * the control it recaps: a label alone already tells the actor a hidden field
 * exists.
 *
 * The geo cascade is recapped by its COMUNE alone — the primary control of the
 * compact address layout, the others being derived ancestors. Its name renders
 * only while the persisted ref still matches the chosen id; after a change the
 * picker's own trigger already names the new one, and inventing a label here
 * would be a guess. Same rule `RegistryFormSummary` applies to the Fonte.
 */
export function OperationalSiteFormSummary({
  control,
  persistedCity,
}: OperationalSiteFormSummaryProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const alias = useWatch({ control, name: 'alias' })
  const line1 = useWatch({ control, name: 'line1' })
  const postalCode = useWatch({ control, name: 'postal_code' })
  const cityId = useWatch({ control, name: 'city_id' })
  const cityName = cityId !== null && persistedCity?.id === cityId ? persistedCity.name : null

  return (
    <FormSection
      icon={Info}
      title={t('operationalSites.detail.summary.title')}
      description={t('operationalSites.detail.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        {fieldPermission('alias').visible ? (
          <SummaryRow label={t('operationalSites.form.alias')}>{alias || EMPTY_VALUE}</SummaryRow>
        ) : null}
        {fieldPermission('line1').visible ? (
          <SummaryRow label={t('operationalSites.form.line1')}>{line1 || EMPTY_VALUE}</SummaryRow>
        ) : null}
        {fieldPermission('postal_code').visible ? (
          <SummaryRow label={t('operationalSites.form.postalCode')}>
            {postalCode || EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('city_id').visible ? (
          <SummaryRow label={t('operationalSites.detail.city')}>
            {cityName ?? EMPTY_VALUE}
          </SummaryRow>
        ) : null}
      </dl>
    </FormSection>
  )
}
