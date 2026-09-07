import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FolderTree } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ROOT_PARENT_VALUE } from '@/features/product-categories/flatten-tree'
import { ProductCategoryBusinessFunctionField } from '@/features/product-categories/product-category-business-function-field'
import type { ParentOptionRef } from '@/features/product-categories/product-category-form-header'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/**
 * Everything the parent picker needs to render, resolved once by the form body
 * (the identity bar and the side recap read the same options) and handed down
 * as one prop instead of four.
 */
export interface ProductCategoryParentPicker {
  options: ParentOptionRef[]
  isPending: boolean
  isError: boolean
  onRetry: () => void
}

interface ProductCategoryIdentitySectionProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  parents: ProductCategoryParentPicker
  /** Current watched `parent_id` — the business function reacts live to it, not to the saved one. */
  parentId: number | null
}

/**
 * What the category IS and where it sits: name and parent side by side on the
 * shared field grid, the description under them at full width because it is
 * prose, then the business function — which the parent above it can make
 * inherited, so it reads right after the field that decides it.
 *
 * Renders nothing when the actor may see none of these fields — an empty card
 * reads as missing data rather than as withheld data.
 */
export function ProductCategoryIdentitySection({
  control,
  mode,
  parents,
  parentId,
}: ProductCategoryIdentitySectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  const visible =
    fieldPermission('name').visible ||
    fieldPermission('parent_id').visible ||
    fieldPermission('description').visible ||
    fieldPermission('business_function_id').visible

  if (!visible) {
    return null
  }

  return (
    <FormSection
      icon={FolderTree}
      title={t('productCategories.form.sections.identity.title')}
      description={t('productCategories.form.sections.identity.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <MetaField
          control={control}
          name="name"
          metaKey="name"
          label={t('productCategories.form.name')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={control}
          name="parent_id"
          metaKey="parent_id"
          label={t('productCategories.form.parent')}
        >
          {({ field, disabled }) => (
            <FormControl>
              <SearchableSelect
                value={field.value ?? ROOT_PARENT_VALUE}
                onChange={(next) => field.onChange(next === ROOT_PARENT_VALUE ? null : next)}
                options={parents.options}
                isPending={parents.isPending}
                isError={parents.isError}
                onRetry={parents.onRetry}
                disabled={disabled}
                labels={{
                  placeholder: t('productCategories.form.parentPlaceholder'),
                  searchPlaceholder: t('productCategories.form.parentSearch'),
                  empty: t('productCategories.form.parentEmpty'),
                  noMatch: t('productCategories.form.parentNoMatch'),
                  error: t('productCategories.form.parentError'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>
      </div>

      <MetaField
        control={control}
        name="description"
        metaKey="description"
        label={t('productCategories.form.description')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea
              disabled={disabled}
              readOnly={readOnly}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>

      <ProductCategoryBusinessFunctionField control={control} mode={mode} parentId={parentId} />
    </FormSection>
  )
}
