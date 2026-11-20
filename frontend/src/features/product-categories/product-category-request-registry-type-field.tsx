import { useEffect, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { IdCard } from 'lucide-react'
import { FormControl, FormDescription } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductCategoryRuleCard } from '@/features/product-categories/product-category-rule-card'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedRequestRegistryType,
  type InheritedRequestRegistryType,
} from '@/features/product-categories/request-registry-type-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import type { PersonalDataType } from '@/features/personal-data/types'

const REGISTRY_TYPES: readonly PersonalDataType[] = ['individual', 'company']

interface ProductCategoryRequestRegistryTypeFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  /** Current watched `parent_id` form value — the field reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * The "Anagrafica in Gestione Richieste" selector (spec 0410): the type the
 * new-client card of a request of this branch starts on. Root-owned like
 * `usage_area`: under a parent the control is read-only and shows the root's
 * value, naming the root it comes from.
 */
export function ProductCategoryRequestRegistryTypeField({
  control,
  mode,
  parentId,
}: ProductCategoryRequestRegistryTypeFieldProps) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()
  const { field } = useController({ control, name: 'request_registry_type' })

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])
  const candidateInheritance = useMemo(
    () => resolveInheritedRequestRegistryType(nodesById, parentId),
    [nodesById, parentId],
  )

  const parentUnchanged = mode.type === 'edit' && parentId === mode.category.parent_id
  const detailFallback: InheritedRequestRegistryType | null =
    parentUnchanged && mode.type === 'edit' && mode.category.request_registry_type_source_category
      ? {
          registryType: mode.category.request_registry_type,
          sourceCategory: mode.category.request_registry_type_source_category,
        }
      : null

  const inheritance = treeQuery.data ? candidateInheritance : detailFallback

  // Keep the RHF value on what will be persisted once a parent makes it inherited.
  useEffect(() => {
    if (inheritance !== null && field.value !== inheritance.registryType) {
      field.onChange(inheritance.registryType)
    }
  }, [inheritance, field])

  const value = inheritance ? inheritance.registryType : field.value

  return (
    <ProductCategoryRuleCard
      icon={IdCard}
      // A company default is the non-standard case: it is what the tinted glyph flags.
      active={value === 'company'}
      inheritedFrom={inheritance?.sourceCategory.name ?? null}
      inheritedLabel={t('productCategories.form.inheritedFrom', { category: inheritance?.sourceCategory.name ?? '' })}
    >
      <MetaField
        control={control}
        name="request_registry_type"
        metaKey="request_registry_type"
        layout="inline"
        label={t('productCategories.form.requestRegistryType')}
        description={
          <FormDescription>
            {inheritance
              ? t('productCategories.form.requestRegistryTypeInheritedHint', { category: inheritance.sourceCategory.name })
              : t('productCategories.form.requestRegistryTypeHint')}
          </FormDescription>
        }
      >
        {({ disabled }) => (
          <Select
            value={value}
            onValueChange={(next) => field.onChange(next as PersonalDataType)}
            disabled={disabled || inheritance !== null}
          >
            <FormControl>
              <SelectTrigger size="sm" className="w-56 max-w-full text-xs">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {REGISTRY_TYPES.map((type) => (
                <SelectItem key={type} value={type}>
                  {t(`enums.personal_data_type.${type}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>
    </ProductCategoryRuleCard>
  )
}
