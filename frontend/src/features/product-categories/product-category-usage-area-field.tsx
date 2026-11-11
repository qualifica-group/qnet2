import { useEffect, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { LayoutGrid } from 'lucide-react'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { resolveInheritedUsageArea, type InheritedUsageArea } from '@/features/product-categories/usage-area-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { CategoryUsageArea, ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

const USAGE_AREAS: readonly CategoryUsageArea[] = ['all', 'requests', 'opportunities']

interface ProductCategoryUsageAreaFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "Visibile in" selector (spec 0389): the modules the branch is picked
 * in. Root-owned like `management_mode`: under a parent the control is
 * read-only and shows the root's value, naming the root it comes from.
 */
export function ProductCategoryUsageAreaField({ control, mode, parentId }: ProductCategoryUsageAreaFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const { field } = useController({ control, name: 'usage_area' })

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const candidateInheritance = useMemo(() => resolveInheritedUsageArea(nodesById, parentId), [nodesById, parentId])

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: InheritedUsageArea | null =
    parentUnchanged && mode.type === 'edit' && mode.category.usage_area_source_category
      ? { usageArea: mode.category.usage_area, sourceCategory: mode.category.usage_area_source_category }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  // Keep the RHF value on what will be persisted once a parent makes it inherited.
  useEffect(() => {
    if (inheritance !== null && field.value !== inheritance.usageArea) {
      field.onChange(inheritance.usageArea)
    }
  }, [inheritance, field])

  const value = inheritance ? inheritance.usageArea : field.value

  return (
    <ProductCategoryRuleCard
      icon={LayoutGrid}
      // A restricted branch is the constraining case: it is what the tinted glyph flags.
      active={value !== 'all'}
      inheritedFrom={inheritance?.sourceCategory.name ?? null}
      inheritedLabel={t('productCategories.form.inheritedFrom', { category: inheritance?.sourceCategory.name ?? '' })}
    >
      <MetaField
        control={control}
        name="usage_area"
        metaKey="usage_area"
        layout="inline"
        label={t('productCategories.form.usageArea')}
        description={
          <FormDescription>
            {inheritance
              ? t('productCategories.form.usageAreaInheritedHint', { category: inheritance.sourceCategory.name })
              : t('productCategories.form.usageAreaHint')}
          </FormDescription>
        }
      >
        {({ disabled }) => (
          <Select
            value={value}
            onValueChange={(next) => field.onChange(next as CategoryUsageArea)}
            disabled={disabled || inheritance !== null}
          >
            <FormControl>
              <SelectTrigger size="sm" className="w-56 max-w-full text-xs">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {USAGE_AREAS.map((area) => (
                <SelectItem key={area} value={area}>
                  {t(`enums.category_usage_area.${area}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}
