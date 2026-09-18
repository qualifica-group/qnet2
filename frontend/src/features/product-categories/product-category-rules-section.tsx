import { useController, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ChartNoAxesColumn, MousePointerClick, SlidersHorizontal } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Badge } from '@/components/ui/badge'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProductCategoryGeneratesContractField } from '@/features/product-categories/product-category-generates-contract-field'
import { ProductCategoryManagementModeField } from '@/features/product-categories/product-category-management-mode-field'
import { ProductCategoryRequiresQuoteField } from '@/features/product-categories/product-category-requires-quote-field'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { ProductCategorySimplifiedOfferLineField } from '@/features/product-categories/product-category-simplified-offer-line-field'
import { ProductCategorySingleQuoteField } from '@/features/product-categories/product-category-single-quote-field'
import { reportableOverrideFor } from '@/features/product-categories/reportable-inheritance'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import { useReportableInheritance } from '@/features/product-categories/use-reportable-inheritance'

interface ProductCategoryRulesSectionProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — every root-owned rule reacts live to it. */
  parentId: number | null
}

/**
 * "Regole di gestione" (user directive 2026-08-07): the behavioural rules a
 * category imposes downstream — whether it is quoted, how many product lines
 * a card carries, how many offers an opportunity may hold, whether a closed
 * deal becomes a contract (spec 0091), whether Gestione Richieste compiles
 * its offer row automatically (spec 0114), and whether it can be picked at
 * all. They used to sit mixed into the identity fields, where an operator
 * could not tell an inert label from a rule that changes what the system
 * accepts.
 *
 * Every rule but `is_selectable`/`is_reportable` is owned by the branch ROOT
 * and inherited by the whole subtree; each carries an (i) tooltip explaining
 * what turning it on actually does. `is_selectable` is a plain per-node flag;
 * `is_reportable` is inherited from the nearest ancestor but can be forced on
 * any node (ReportableRule).
 *
 * Two columns from `sm:` up: the tiles stay readable at 375px and the section
 * does not become a tall stack on a desktop config screen.
 */
export function ProductCategoryRulesSection({
  control,
  mode,
  parentId,
}: ProductCategoryRulesSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  const visible =
    fieldPermission('requires_quote').visible ||
    fieldPermission('management_mode').visible ||
    fieldPermission('single_quote_per_opportunity').visible ||
    fieldPermission('generates_contract').visible ||
    fieldPermission('simplified_offer_line').visible ||
    fieldPermission('is_selectable').visible ||
    fieldPermission('is_reportable').visible

  if (!visible) {
    return null
  }

  return (
    <FormSection
      icon={SlidersHorizontal}
      title={t('productCategories.form.sections.rules.title')}
      description={t('productCategories.form.sections.rules.description')}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <ProductCategoryRequiresQuoteField control={control} mode={mode} parentId={parentId} />
        <ProductCategoryManagementModeField control={control} mode={mode} parentId={parentId} />
        <ProductCategorySingleQuoteField control={control} mode={mode} parentId={parentId} />
        <ProductCategoryGeneratesContractField control={control} mode={mode} parentId={parentId} />
        <ProductCategorySimplifiedOfferLineField control={control} mode={mode} parentId={parentId} />

        <SelectableRule control={control} />
        <ReportableRule control={control} mode={mode} />
      </div>
    </FormSection>
  )
}

interface SelectableRuleProps {
  control: Control<ProductCategoryFormValues>
}

/**
 * `is_selectable` as a rule tile. Per-node and never inherited (spec 0074), so
 * it needs none of the root-owned chrome — defined at module level, never
 * inside the section component.
 */
function SelectableRule({ control }: SelectableRuleProps) {
  const { t } = useTranslation()
  const { field } = useController({ control, name: 'is_selectable' })

  return (
    <ProductCategoryRuleCard icon={MousePointerClick} active={field.value}>
      <MetaField
        control={control}
        name="is_selectable"
        metaKey="is_selectable"
        layout="inline"
        label={t('productCategories.form.isSelectable')}
        hint={t('productCategories.form.isSelectableInfo')}
        hintLabel={t('productCategories.form.isSelectableInfoLabel')}
        description={
          <FormDescription>{t('productCategories.form.isSelectableHint')}</FormDescription>
        }
      >
        {({ field: switchField, disabled }) => (
          <FormControl>
            <Switch
              checked={switchField.value}
              onCheckedChange={switchField.onChange}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}

interface ReportableRuleProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
}

/**
 * `is_reportable` as a rule tile (user directive 2026-09-18): a child inherits
 * its parent's value by default and the switch forces it. The switch shows the
 * EFFECTIVE value; setting it back to the inherited one stores null again
 * (reportableOverrideFor), so there is no separate reset control.
 */
function ReportableRule({ control, mode }: ReportableRuleProps) {
  const { t } = useTranslation()
  const { override, inherited, effective } = useReportableInheritance(control, mode)
  const sourceName = inherited?.sourceCategory?.name ?? null
  const forced = override !== null && inherited !== null

  let description = t('productCategories.form.isReportableHint')
  if (forced) {
    description = t('productCategories.form.isReportableForcedHint', { category: sourceName ?? '' })
  } else if (sourceName !== null) {
    description = t('productCategories.form.isReportableInheritedHint', { category: sourceName })
  }

  return (
    <ProductCategoryRuleCard icon={ChartNoAxesColumn} active={effective}>
      <MetaField
        control={control}
        name="is_reportable"
        metaKey="is_reportable"
        layout="inline"
        label={t('productCategories.form.isReportable')}
        hint={t('productCategories.form.isReportableInfo')}
        hintLabel={t('productCategories.form.isReportableInfoLabel')}
        description={<FormDescription>{description}</FormDescription>}
      >
        {({ field: switchField, disabled }) => (
          <FormControl>
            <Switch
              checked={effective}
              onCheckedChange={(checked) => switchField.onChange(reportableOverrideFor(checked, inherited))}
              disabled={disabled}
            />
          </FormControl>
        )}
      </MetaField>
      {forced ? (
        <Badge variant="outline" className="text-[11px] font-normal">
          {t('productCategories.form.isReportableForcedBadge')}
        </Badge>
      ) : null}
      {!forced && sourceName !== null ? (
        <Badge variant="outline" className="text-[11px] font-normal">
          {t('productCategories.form.inheritedFrom', { category: sourceName })}
        </Badge>
      ) : null}
    </ProductCategoryRuleCard>
  )
}
