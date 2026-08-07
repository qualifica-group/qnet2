import { useEffect, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { Layers } from 'lucide-react'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedManagementMode,
  type InheritedManagementMode,
} from '@/features/product-categories/management-mode-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type {
  CategoryManagementMode,
  ProductCategoryFormMode,
} from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface ProductCategoryManagementModeFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "single vs. multiple Category Product lines per card" selector (spec
 * 0077). The value belongs to the branch ROOT: as soon as a parent is
 * SELECTED (not necessarily the saved one) the control turns read-only and
 * displays the root's value, naming the root it comes from. Gemello of
 * `ProductCategoryRequiresQuoteField` — same inheritance shape, same tree
 * cache, zero extra requests.
 *
 * While the tree has not resolved yet, an edit-mode category whose
 * `parent_id` is still the loaded one falls back to the detail's own
 * `management_mode_source_category` rather than flash an editable control;
 * the tree takes over as soon as it is available.
 */
export function ProductCategoryManagementModeField({
  control,
  mode,
  parentId,
}: ProductCategoryManagementModeFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const { field } = useController({ control, name: 'management_mode' })

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const candidateInheritance = useMemo(
    () => resolveInheritedManagementMode(nodesById, parentId),
    [nodesById, parentId],
  )

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: InheritedManagementMode | null =
    parentUnchanged && mode.type === 'edit' && mode.category.management_mode_source_category
      ? {
          managementMode: mode.category.management_mode,
          sourceCategory: mode.category.management_mode_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback
  const inherited = inheritance !== null

  // Keep the RHF value on what will actually be persisted once a parent makes
  // the value inherited: the payload builders drop it for a non-root
  // category, but the control must not keep showing a value the save would
  // discard.
  useEffect(() => {
    if (inheritance !== null && field.value !== inheritance.managementMode) {
      field.onChange(inheritance.managementMode)
    }
  }, [inheritance, field])

  const value = inheritance ? inheritance.managementMode : field.value

  return (
    <ProductCategoryRuleCard
      icon={Layers}
      // "single" is the constraining mode: it is what the tinted glyph flags.
      active={value === 'single'}
      inheritedFrom={inheritance?.sourceCategory.name ?? null}
      inheritedLabel={t('productCategories.form.inheritedFrom', {
        category: inheritance?.sourceCategory.name ?? '',
      })}
    >
      <MetaField
        control={control}
        name="management_mode"
        metaKey="management_mode"
        layout="inline"
        label={t('productCategories.form.managementMode')}
        hint={t('productCategories.form.managementModeInfo')}
        hintLabel={t('productCategories.form.managementModeInfoLabel')}
        description={
          <FormDescription>
            {inheritance
              ? t('productCategories.form.managementModeInheritedHint', {
                  category: inheritance.sourceCategory.name,
                })
              : t('productCategories.form.managementModeHint')}
          </FormDescription>
        }
      >
        {({ disabled }) => (
          <Select
            value={value}
            onValueChange={(next) => field.onChange(next as CategoryManagementMode)}
            disabled={disabled || inherited}
          >
            <FormControl>
              <SelectTrigger size="sm" className="w-44 max-w-full text-xs">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              <SelectItem value="single">{t('productCategories.form.managementModeSingle')}</SelectItem>
              <SelectItem value="multiple">
                {t('productCategories.form.managementModeMultiple')}
              </SelectItem>
            </SelectContent>
          </Select>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}
