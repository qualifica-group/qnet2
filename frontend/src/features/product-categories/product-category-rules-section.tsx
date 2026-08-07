import { useController, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { MousePointerClick, SlidersHorizontal } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Switch } from '@/components/ui/switch'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProductCategoryManagementModeField } from '@/features/product-categories/product-category-management-mode-field'
import { ProductCategoryRequiresQuoteField } from '@/features/product-categories/product-category-requires-quote-field'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { ProductCategorySingleQuoteField } from '@/features/product-categories/product-category-single-quote-field'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryRulesSectionProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — every root-owned rule reacts live to it. */
  parentId: number | null
}

/**
 * "Regole di gestione" (user directive 2026-08-07): the behavioural rules a
 * category imposes downstream — whether it is quoted, how many product lines
 * a card carries, how many offers an opportunity may hold, and whether it can
 * be picked at all. They used to sit mixed into the identity fields, where an
 * operator could not tell an inert label from a rule that changes what the
 * system accepts.
 *
 * Every rule but `is_selectable` is owned by the branch ROOT and inherited by
 * the whole subtree; each carries an (i) tooltip explaining what turning it on
 * actually does. `is_selectable` is the odd one out — a plain per-node flag —
 * so it renders here without the inheritance chrome.
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
    fieldPermission('is_selectable').visible

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

        <SelectableRule control={control} />
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
