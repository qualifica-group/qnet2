import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import { formatDecimal } from '@/features/products/column-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunitySelectedItems } from '@/features/opportunities/use-opportunity-selected-items'

interface OpportunityFormSummaryProps {
  control: Control<OpportunityFormValues>
  /** Hydrated relation refs: the only source of a NAME for the ids the form holds. */
  selectedItems: OpportunitySelectedItems
}

/**
 * The form's side-column recap, in the SAME card and the SAME `label / value`
 * rows as the Gestione Richieste summaries (`SummaryRow`, user directive
 * 2026-08-05) — so the screens have the same shape, not just the same
 * sections.
 *
 * Every row is read LIVE from the form: it recaps what is about to be saved,
 * never the persisted snapshot, so it cannot contradict the fields on the left
 * while they are being edited. A relation renders its name only while the
 * hydrated ref still matches the chosen id; after a change the picker's own
 * trigger already names the new one, and inventing a label here would be a
 * guess.
 */
export function OpportunityFormSummary({ control, selectedItems }: OpportunityFormSummaryProps) {
  const { t } = useTranslation()
  const registryId = useWatch({ control, name: 'registry_id' })
  const productLines = useWatch({ control, name: 'product_lines' })
  const productsOfInterest = useWatch({ control, name: 'products_of_interest' })
  const estimatedValue = useWatch({ control, name: 'estimated_value' })
  const expectedCloseDate = useWatch({ control, name: 'expected_close_date' })
  const successProbability = useWatch({ control, name: 'success_probability' })

  const completeLines = productLines.filter(
    (row) => row.business_function_id !== null && row.product_category_id !== null,
  ).length
  const registryName =
    registryId !== null && selectedItems.registry?.id === registryId ? selectedItems.registry.name : null

  return (
    <FormSection
      icon={Info}
      title={t('opportunities.form.summary.title')}
      description={t('opportunities.form.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        <SummaryRow label={t('opportunities.form.registry')}>{registryName ?? EMPTY_VALUE}</SummaryRow>
        <SummaryRow label={t('opportunities.form.sections.productLines.title')}>
          {completeLines > 0 ? completeLines : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('products.ofInterest.sectionTitle')}>
          {productsOfInterest.length > 0 ? productsOfInterest.length : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('opportunities.form.estimatedValue')}>
          {formatDecimal(estimatedValue) || EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('opportunities.form.expectedCloseDate')}>
          {formatDate(expectedCloseDate) || EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('opportunities.form.successProbability')}>{`${successProbability}%`}</SummaryRow>
      </dl>
    </FormSection>
  )
}
