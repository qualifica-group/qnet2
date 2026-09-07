import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Info } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { EMPTY_VALUE, SUMMARY_LIST_CLASS, SummaryRow } from '@/components/record-form/record-summary'
import type { ParentOptionRef } from '@/features/product-categories/product-category-form-header'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryFormSummaryProps {
  control: Control<ProductCategoryFormValues>
  /** The parent picker's own option list: the only place a parent id has a name here. */
  parentOptions: readonly ParentOptionRef[]
}

/**
 * The form's side-column recap, in the SAME card and the SAME `label / value`
 * rows as the other record screens (`SummaryRow`).
 *
 * A category is mostly RULES, and each of them is a switch buried in its own
 * tile: read one at a time they never add up to "what will this category
 * impose downstream". This panel is that answer, live from the form — so the
 * five behavioural flags can be checked together before saving, without
 * scrolling back through the section.
 */
export function ProductCategoryFormSummary({
  control,
  parentOptions,
}: ProductCategoryFormSummaryProps) {
  const { t } = useTranslation()
  const parentId = useWatch({ control, name: 'parent_id' })
  const requiresQuote = useWatch({ control, name: 'requires_quote' })
  const managementMode = useWatch({ control, name: 'management_mode' })
  const singleQuote = useWatch({ control, name: 'single_quote_per_opportunity' })
  const generatesContract = useWatch({ control, name: 'generates_contract' })
  const isSelectable = useWatch({ control, name: 'is_selectable' })
  const attributes = useWatch({ control, name: 'attributes' })
  const managerLabels = useWatch({ control, name: 'manager_labels' })

  const parentName =
    parentId === null ? null : (parentOptions.find((option) => option.id === parentId)?.name ?? null)
  // Blank rows are padding the editor opens with, not configured levels: the
  // payload builder strips them, so the recap must not count them either.
  const managerLevels = Object.values(managerLabels).filter((label) => label.trim() !== '').length
  const yesNo = (value: boolean) => (value ? t('common.yes') : t('common.no'))

  return (
    <FormSection
      icon={Info}
      title={t('productCategories.form.summary.title')}
      description={t('productCategories.form.summary.description')}
      className="min-w-0"
    >
      <dl className={SUMMARY_LIST_CLASS}>
        <SummaryRow label={t('productCategories.form.parent')}>
          {parentName ?? t('productCategories.badges.root')}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.requiresQuote')}>
          {yesNo(requiresQuote)}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.managementMode')}>
          {managementMode === 'single'
            ? t('productCategories.form.managementModeSingleShort')
            : t('productCategories.form.managementModeMultipleShort')}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.singleQuotePerOpportunity')}>
          {yesNo(singleQuote)}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.generatesContract')}>
          {yesNo(generatesContract)}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.isSelectable')}>
          {yesNo(isSelectable)}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.attributes')}>
          {attributes.length > 0 ? attributes.length : EMPTY_VALUE}
        </SummaryRow>
        <SummaryRow label={t('productCategories.form.sections.managerLabels.title')}>
          {managerLevels > 0 ? managerLevels : EMPTY_VALUE}
        </SummaryRow>
      </dl>
    </FormSection>
  )
}
