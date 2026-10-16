import type { ReactNode } from 'react'
import { useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { ProductCategoryGeneratesContractField } from '@/features/product-categories/product-category-generates-contract-field'
import { ProductCategoryManagementModeField } from '@/features/product-categories/product-category-management-mode-field'
import { ProductCategoryMultiQuoteWorkOrderField } from '@/features/product-categories/product-category-multi-quote-work-order-field'
import { ProductCategoryReportColumnsField } from '@/features/product-categories/product-category-report-columns-field'
import { ProductCategoryRequiresQuoteField } from '@/features/product-categories/product-category-requires-quote-field'
import { ProductCategorySimplifiedOfferLineField } from '@/features/product-categories/product-category-simplified-offer-line-field'
import { ProductCategorySingleQuoteField } from '@/features/product-categories/product-category-single-quote-field'
import { ActiveRule, ReportableRule, SelectableRule } from '@/features/product-categories/product-category-flag-rules'
import type { ProductCategoryRecordSectionProps } from '@/features/product-categories/product-category-record-props'
import { ReportColumnsValue, RuleValue } from '@/features/product-categories/product-category-rule-values'
import { useReportColumnsCatalog } from '@/features/product-categories/use-report-columns-catalog'
import type { InlineEdit } from '@/components/record-form/record-inline-field'

interface RuleRowProps {
  field: string
  label: string
  inline: InlineEdit
  /** Editing allowed (update permission, rule not locked to its branch root). */
  canEdit: boolean
  value: string
  /** The category the value comes from, shown as a chip while it is not this category's own. */
  inheritedFrom?: string
  inheritedLabel?: string
  editor: ReactNode
}

/** One rule of the section: its resolved value (with the "inherited from" chip) opening on the form's own tile. */
function RuleRow({ field, label, inline, canEdit, value, inheritedFrom, inheritedLabel, editor }: RuleRowProps) {
  return (
    <RecordInlineField field={field} label={label} inline={inline} canEdit={canEdit} editor={editor}>
      <RuleValue value={value} inheritedFrom={inheritedFrom} inheritedLabel={inheritedLabel} />
    </RecordInlineField>
  )
}

/**
 * The behavioural rules a category imposes downstream (spec 0229 D-3), one row
 * per rule in the order of the form's old "Regole di gestione" section. Every
 * rule but `is_selectable`/`is_active`/`is_reportable`/the report columns is
 * owned by the branch ROOT: under a parent it only displays, naming the root,
 * so it offers no pencil. The report columns row appears while the category is
 * effectively reportable (spec 0141).
 */
export function ProductCategoryRulesRecordSection({ values, form, inline, mode, canEdit }: ProductCategoryRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  // Every root-owned editor reacts live to the watched parent, not to the saved one.
  const parentId = useWatch({ control, name: 'parent_id' })
  const catalogQuery = useReportColumnsCatalog(values.effective_report_columns.length > 0)
  const labelByKey = new Map((catalogQuery.data ?? []).map((option) => [option.key, option.label]))
  const yesNo = (value: boolean) => (value ? t('common.yes') : t('common.no'))
  const editorProps = { control, mode, parentId }
  const inheritedLabel = (key: string, source: { name: string } | null) =>
    t(`productCategories.detail.${key}`, { category: source?.name ?? '' })

  return (
    <RecordSection title={t('productCategories.form.sections.rules.title')} icon={<SlidersHorizontal />} full>
      <RecordFieldList>
        <RuleRow
          field="requires_quote"
          label={t('productCategories.form.requiresQuote')}
          inline={inline}
          canEdit={canEdit && values.requires_quote_source_category === null}
          value={yesNo(values.requires_quote)}
          inheritedFrom={values.requires_quote_source_category?.name}
          inheritedLabel={inheritedLabel('requiresQuoteInherited', values.requires_quote_source_category)}
          editor={<ProductCategoryRequiresQuoteField {...editorProps} />}
        />
        <RuleRow
          field="management_mode"
          label={t('productCategories.form.managementMode')}
          inline={inline}
          canEdit={canEdit && values.management_mode_source_category === null}
          value={
            values.management_mode === 'single'
              ? t('productCategories.form.managementModeSingle')
              : t('productCategories.form.managementModeMultiple')
          }
          inheritedFrom={values.management_mode_source_category?.name}
          inheritedLabel={inheritedLabel('managementModeInherited', values.management_mode_source_category)}
          editor={<ProductCategoryManagementModeField {...editorProps} />}
        />
        <RuleRow
          field="single_quote_per_opportunity"
          label={t('productCategories.form.singleQuotePerOpportunity')}
          inline={inline}
          canEdit={canEdit && values.single_quote_per_opportunity_source_category === null}
          value={yesNo(values.single_quote_per_opportunity)}
          inheritedFrom={values.single_quote_per_opportunity_source_category?.name}
          inheritedLabel={inheritedLabel(
            'singleQuotePerOpportunityInherited',
            values.single_quote_per_opportunity_source_category,
          )}
          editor={<ProductCategorySingleQuoteField {...editorProps} />}
        />
        <RuleRow
          field="generates_contract"
          label={t('productCategories.form.generatesContract')}
          inline={inline}
          canEdit={canEdit && values.generates_contract_source_category === null}
          value={yesNo(values.generates_contract)}
          inheritedFrom={values.generates_contract_source_category?.name}
          inheritedLabel={inheritedLabel('generatesContractInherited', values.generates_contract_source_category)}
          editor={<ProductCategoryGeneratesContractField {...editorProps} />}
        />
        <RuleRow
          field="multi_quote_work_order"
          label={t('productCategories.form.multiQuoteWorkOrder')}
          inline={inline}
          canEdit={canEdit && values.multi_quote_work_order_source_category === null}
          value={yesNo(values.multi_quote_work_order)}
          inheritedFrom={values.multi_quote_work_order_source_category?.name}
          inheritedLabel={inheritedLabel('multiQuoteWorkOrderInherited', values.multi_quote_work_order_source_category)}
          editor={<ProductCategoryMultiQuoteWorkOrderField {...editorProps} />}
        />
        {/* A root declares the value, a child forces its own override (spec 0188): two form fields, one row. */}
        <RuleRow
          field={values.parent === null ? 'simplified_offer_line' : 'simplified_offer_line_override'}
          label={t('productCategories.form.simplifiedOfferLine')}
          inline={inline}
          canEdit={canEdit}
          value={yesNo(values.simplified_offer_line)}
          inheritedFrom={values.simplified_offer_line_source_category?.name}
          inheritedLabel={inheritedLabel('simplifiedOfferLineInherited', values.simplified_offer_line_source_category)}
          editor={<ProductCategorySimplifiedOfferLineField {...editorProps} />}
        />
        <RuleRow
          field="is_selectable"
          label={t('productCategories.form.isSelectable')}
          inline={inline}
          canEdit={canEdit}
          value={yesNo(values.is_selectable)}
          editor={<SelectableRule control={control} />}
        />
        <RuleRow
          field="is_active"
          label={t('productCategories.form.isActive')}
          inline={inline}
          canEdit={canEdit}
          value={yesNo(values.is_active)}
          editor={<ActiveRule control={control} />}
        />
        <RuleRow
          field="is_reportable"
          label={t('productCategories.form.isReportable')}
          inline={inline}
          canEdit={canEdit}
          value={yesNo(values.effective_is_reportable)}
          inheritedFrom={values.is_reportable === null ? values.is_reportable_source_category?.name : undefined}
          inheritedLabel={inheritedLabel('isReportableInherited', values.is_reportable_source_category)}
          editor={<ReportableRule control={control} mode={mode} />}
        />
        {values.effective_is_reportable ? (
          <RecordInlineField
            field="report_columns"
            label={t('productCategories.form.reportColumns')}
            inline={inline}
            canEdit={canEdit}
            editor={<ProductCategoryReportColumnsField control={control} mode={mode} />}
          >
            <ReportColumnsValue
              columns={values.effective_report_columns}
              labelByKey={labelByKey}
              inheritedFrom={values.report_columns === null ? (values.report_columns_source_category?.name ?? null) : null}
              inheritedLabel={inheritedLabel('reportColumnsInherited', values.report_columns_source_category)}
            />
          </RecordInlineField>
        ) : null}
      </RecordFieldList>
    </RecordSection>
  )
}
