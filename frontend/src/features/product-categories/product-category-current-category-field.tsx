import { useTranslation } from 'react-i18next'
import { useController, type Control } from 'react-hook-form'
import { FormControl, FormDescription } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import { PRODUCT_CATEGORY_ROOTS_FOR_SELECT_RESOURCE } from '@/features/product-categories/for-select-api'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Only the current (non-legacy) roots are offered, and the "Tutte" entry is left out (spec 0374 D-9). */
const CURRENT_ONLY_PARAMS = { current_only: 1 }

interface ProductCategoryCurrentCategoryFieldProps {
  control: Control<ProductCategoryFormValues>
  /** The saved current root, so the trigger shows its name without a lookup. */
  selected: { id: number; name: string } | null
}

/** The "Categoria attuale" picker of a legacy root (spec 0374 D-9): clearable, gated by the field permission. */
export function ProductCategoryCurrentCategoryField({ control, selected }: ProductCategoryCurrentCategoryFieldProps) {
  const { t } = useTranslation()
  const { field } = useController({ control, name: 'current_category_id' })
  const selectedItem: ForSelectItem | null = selected ? { id: selected.id, label: selected.name } : null

  return (
    <MetaField
      control={control}
      name="current_category_id"
      metaKey="current_category_id"
      label={t('productCategories.form.currentCategory')}
      description={<FormDescription>{t('productCategories.form.currentCategoryHint')}</FormDescription>}
    >
      {({ disabled }) => (
        <FormControl>
          <AsyncPaginatedSelect
            resource={PRODUCT_CATEGORY_ROOTS_FOR_SELECT_RESOURCE}
            params={CURRENT_ONLY_PARAMS}
            value={field.value ?? null}
            onChange={field.onChange}
            selectedItem={selectedItem}
            disabled={disabled}
            labels={{
              placeholder: t('productCategories.form.currentCategoryPlaceholder'),
              searchPlaceholder: t('productCategories.form.currentCategorySearch'),
              empty: t('productCategories.form.currentCategoryEmpty'),
              error: t('productCategories.form.currentCategoryError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('productCategories.form.currentCategory'),
              retry: t('common.retry'),
            }}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
