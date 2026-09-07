import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FolderTree } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { SearchableSelect } from '@/components/ui/searchable-select'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useEnumOptions } from '@/features/config/use-config'
import type { FlatCategoryOption } from '@/features/product-categories/flatten-tree'
import { PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE } from '@/features/product-typologies/for-select-api'
import { UNITS_OF_MEASURE_FOR_SELECT_RESOURCE } from '@/features/units-of-measure/for-select-api'
import type { ProductSelectedRelations } from '@/features/products/product-form-summary'
import type { ProductFormValues } from '@/features/products/use-product-form'
import type { ProductType } from '@/features/products/types'

/**
 * Everything the category picker needs to render, resolved once by the form
 * body (the header and the side recap read the same options) and handed down
 * as one prop instead of four.
 */
export interface ProductCategoryPicker {
  options: FlatCategoryOption[]
  isPending: boolean
  isError: boolean
  onRetry: () => void
}

interface ProductClassificationSectionProps {
  control: Control<ProductFormValues>
  categories: ProductCategoryPicker
  /** Hydrated relation refs of the loaded product; `null` members in create. */
  selected: ProductSelectedRelations
  /** Kept alongside `field.onChange`: the category drives the dynamic attribute set (spec 0061). */
  onCategoryChange: (next: number) => void
}

/**
 * Where the product SITS in the catalogue: category, typology, unit of
 * measure and the `product_type` enum, two per row on the shared field grid.
 * The category comes first because it is the field the rest of the form
 * depends on — it decides which dynamic attributes exist below.
 */
export function ProductClassificationSection({
  control,
  categories,
  selected,
  onCategoryChange,
}: ProductClassificationSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const productTypeOptions = useEnumOptions('product_type')

  const visible =
    fieldPermission('category_id').visible ||
    fieldPermission('product_typology_id').visible ||
    fieldPermission('unit_of_measure_id').visible ||
    fieldPermission('product_type').visible

  if (!visible) {
    return null
  }

  return (
    <FormSection
      icon={FolderTree}
      title={t('products.form.sections.classification.title')}
      description={t('products.form.sections.classification.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <MetaField
          control={control}
          name="category_id"
          metaKey="category_id"
          label={t('products.form.category')}
        >
          {({ field, disabled }) => (
            <FormControl>
              <SearchableSelect
                value={field.value}
                onChange={(next) => {
                  field.onChange(next)
                  onCategoryChange(next)
                }}
                options={categories.options}
                isPending={categories.isPending}
                isError={categories.isError}
                onRetry={categories.onRetry}
                disabled={disabled}
                labels={{
                  placeholder: t('products.form.categoryPlaceholder'),
                  searchPlaceholder: t('products.form.categorySearch'),
                  empty: t('products.form.categoryEmpty'),
                  noMatch: t('products.form.categoryNoMatch'),
                  error: t('products.form.categoryError'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <RelationSelectField
          control={control}
          name="product_typology_id"
          metaKey="product_typology_id"
          label={t('products.form.productTypology')}
          resource={PRODUCT_TYPOLOGIES_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('products.form.productTypologySearch')}
          selected={selected.productTypology}
          placeholder={t('products.form.productTypologyPlaceholder')}
          emptyLabel={t('products.form.productTypologyEmpty')}
          errorLabel={t('products.form.productTypologyError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />

        <RelationSelectField
          control={control}
          name="unit_of_measure_id"
          metaKey="unit_of_measure_id"
          label={t('products.form.unitOfMeasure')}
          resource={UNITS_OF_MEASURE_FOR_SELECT_RESOURCE}
          searchPlaceholder={t('products.form.unitOfMeasureSearch')}
          selected={selected.unitOfMeasure}
          placeholder={t('products.form.unitOfMeasurePlaceholder')}
          emptyLabel={t('products.form.unitOfMeasureEmpty')}
          errorLabel={t('products.form.unitOfMeasureError')}
          clearLabel={t('common.clear')}
          retryLabel={t('common.retry')}
        />

        <MetaField
          control={control}
          name="product_type"
          metaKey="product_type"
          label={t('products.form.productType')}
        >
          {({ field, disabled }) => (
            <Select
              value={field.value}
              onValueChange={(next) => field.onChange(next as ProductType)}
              disabled={disabled}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {productTypeOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      </div>
    </FormSection>
  )
}
