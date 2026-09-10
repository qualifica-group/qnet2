import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { Badge } from '@/components/ui/badge'
import type { ProductCategoryDetailWithPermissions } from '@/features/product-categories/types'

interface RuleValueProps {
  value: string
  /** Set when the rule is inherited from a branch root — renders the chip naming it. */
  inheritedFrom?: string
  inheritedLabel?: string
}

/** One rule's resolved value, plus the "inherited from X" chip when it is not this category's own. */
function RuleValue({ value, inheritedFrom, inheritedLabel }: RuleValueProps) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span>{value}</span>
      {inheritedFrom ? (
        <Badge variant="outline" className="text-xs">
          {inheritedLabel}
        </Badge>
      ) : null}
    </div>
  )
}

interface ProductCategoryDetailRulesProps {
  category: ProductCategoryDetailWithPermissions
}

/**
 * The behavioural rules a category imposes downstream, read-only: the same
 * grouping the form's "Regole di gestione" section uses (user directive
 * 2026-08-07), so the two screens name and order them identically instead of
 * scattering five one-field blocks.
 *
 * Every rule but `is_selectable` is owned by the branch ROOT and mirrored on
 * the whole subtree, so each carries the chip naming the category it comes
 * from; `is_selectable` is per-node and never inherited (spec 0074), hence no
 * chip.
 */
export function ProductCategoryDetailRules({ category }: ProductCategoryDetailRulesProps) {
  const { t } = useTranslation()
  const yesNo = (value: boolean) => (value ? t('common.yes') : t('common.no'))

  return (
    <RecordSection
      title={t('productCategories.form.sections.rules.title')}
      icon={<SlidersHorizontal />}
      full
    >
      <RecordFieldList>
        <RecordField label={t('productCategories.form.requiresQuote')}>
          <RuleValue
            value={yesNo(category.requires_quote)}
            inheritedFrom={category.requires_quote_source_category?.name}
            inheritedLabel={t('productCategories.detail.requiresQuoteInherited', {
              category: category.requires_quote_source_category?.name ?? '',
            })}
          />
        </RecordField>

        <RecordField label={t('productCategories.form.managementMode')}>
          <RuleValue
            value={
              category.management_mode === 'single'
                ? t('productCategories.form.managementModeSingle')
                : t('productCategories.form.managementModeMultiple')
            }
            inheritedFrom={category.management_mode_source_category?.name}
            inheritedLabel={t('productCategories.detail.managementModeInherited', {
              category: category.management_mode_source_category?.name ?? '',
            })}
          />
        </RecordField>

        <RecordField label={t('productCategories.form.singleQuotePerOpportunity')}>
          <RuleValue
            value={yesNo(category.single_quote_per_opportunity)}
            inheritedFrom={category.single_quote_per_opportunity_source_category?.name}
            inheritedLabel={t('productCategories.detail.singleQuotePerOpportunityInherited', {
              category: category.single_quote_per_opportunity_source_category?.name ?? '',
            })}
          />
        </RecordField>

        <RecordField label={t('productCategories.form.generatesContract')}>
          <RuleValue
            value={yesNo(category.generates_contract)}
            inheritedFrom={category.generates_contract_source_category?.name}
            inheritedLabel={t('productCategories.detail.generatesContractInherited', {
              category: category.generates_contract_source_category?.name ?? '',
            })}
          />
        </RecordField>

        <RecordField label={t('productCategories.form.simplifiedOfferLine')}>
          <RuleValue
            value={yesNo(category.simplified_offer_line)}
            inheritedFrom={category.simplified_offer_line_source_category?.name}
            inheritedLabel={t('productCategories.detail.simplifiedOfferLineInherited', {
              category: category.simplified_offer_line_source_category?.name ?? '',
            })}
          />
        </RecordField>

        <RecordField label={t('productCategories.form.isSelectable')}>
          <RuleValue value={yesNo(category.is_selectable)} />
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}
