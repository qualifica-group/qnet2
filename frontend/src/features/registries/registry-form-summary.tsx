import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { enumLabelOf } from '@/features/config/enum-label'
import type { RegistrySelectedItems } from '@/features/registries/registry-form-details-tab'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

interface RegistryFormSummaryProps {
  control: Control<RegistryFormValues>
  /** Hydrated relation refs: the only source of a NAME for the ids the form holds. */
  selectedItems: RegistrySelectedItems
}

/**
 * The form's side-column recap, in the same card and the same `label / value`
 * rows as the Opportunità and Gestione Richieste summaries (`SummaryRow`), so
 * the record forms keep one shape.
 *
 * Every row is read LIVE from the form — it recaps what is about to be saved,
 * never the persisted snapshot — and is gated by the SAME field permission as
 * the control it recaps: a label alone already tells the actor a hidden field
 * exists. The Fonte renders its name only while the hydrated ref still matches
 * the chosen id; after a change the picker's own trigger already names the new
 * one, and inventing a label here would be a guess.
 */
export function RegistryFormSummary({ control, selectedItems }: RegistryFormSummaryProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const sourceId = useWatch({ control, name: 'source_id' })
  const sectorIds = useWatch({ control, name: 'sector_ids' })
  const referentIds = useWatch({ control, name: 'referent_ids' })
  const managerSlots = useWatch({ control, name: 'manager_slots' })
  const agreementStatus = useWatch({ control, name: 'agreement_status' })

  const sourceName =
    sourceId !== null && selectedItems.source?.id === sourceId ? selectedItems.source.label : null
  // An empty slot is a deliberate gap, not a manager: only the filled ones count.
  const filledManagers = managerSlots.filter((slot) => slot !== null).length

  return (
    <FormSection
      icon={Info}
      title={t('registries.detail.summary.title')}
      description={t('registries.detail.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        {fieldPermission('source_id').visible ? (
          <SummaryRow label={t('registries.form.source')}>{sourceName ?? EMPTY_VALUE}</SummaryRow>
        ) : null}
        {fieldPermission('sector_ids').visible ? (
          <SummaryRow label={t('registries.form.sectors')}>
            {sectorIds.length > 0 ? sectorIds.length : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('referent_ids').visible ? (
          <SummaryRow label={t('registries.form.referents')}>
            {referentIds.length > 0 ? referentIds.length : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('manager_slots').visible ? (
          <SummaryRow label={t('registries.form.managers')}>
            {filledManagers > 0 ? filledManagers : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
        {fieldPermission('agreement_status').visible ? (
          <SummaryRow label={t('registries.form.agreementStatus')}>
            {agreementStatus ? enumLabelOf('agreement_status', agreementStatus) : EMPTY_VALUE}
          </SummaryRow>
        ) : null}
      </dl>
    </FormSection>
  )
}
