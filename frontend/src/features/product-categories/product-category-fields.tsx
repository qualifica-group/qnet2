import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { ROOT_PARENT_VALUE } from '@/features/product-categories/flatten-tree'
import type { ProductCategoryParentPicker } from '@/features/product-categories/use-product-category-parent-picker'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface FieldProps {
  control: Control<ProductCategoryFormValues>
}

/** The category name control: the editor of the `name` row, on the detail and on the create draft alike. */
export function ProductCategoryNameField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="name" metaKey="name" label={t('productCategories.form.name')}>
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

interface ParentFieldProps extends FieldProps {
  parents: ProductCategoryParentPicker
}

/** The parent picker: "no parent" is the sentinel option, stored as `null`. */
export function ProductCategoryParentField({ control, parents }: ParentFieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="parent_id" metaKey="parent_id" label={t('productCategories.form.parent')}>
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
  )
}

export function ProductCategoryDescriptionField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name="description" metaKey="description" label={t('productCategories.form.description')}>
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
  )
}
