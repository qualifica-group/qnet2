import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { PackageSearch } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

interface RequestCreateProductsOfInterestProps {
  control: Control<RequestCreateFormValues>
}

/**
 * "Prodotti di interesse" at creation (user directive 2026-07-31): the SAME
 * picker the work panel carries, so a request that already knows its products
 * records them up front. Optional here — nothing is mandatory before the
 * first call.
 *
 * The picker is scoped by the categories of the rows being filled in right
 * above it (`useWatch`), which is also the rule the server enforces: a product
 * outside them is refused (ProductCategoryCoherence). Plain `FormField`,
 * not `MetaField`: this create-only form has no `permissions` envelope to gate
 * against (see `RequestCreateAttributionSection`).
 */
export function RequestCreateProductsOfInterest({ control }: RequestCreateProductsOfInterestProps) {
  const { t } = useTranslation()
  const productLines = useWatch({ control, name: 'product_lines' })

  const categoryIds = useMemo(
    () => [
      ...new Set(
        productLines
          .map((line) => line.product_category_id)
          .filter((id): id is number => id !== null),
      ),
    ],
    [productLines],
  )

  return (
    <FormSection
      icon={PackageSearch}
      title={t('products.ofInterest.sectionTitle')}
      description={t('products.ofInterest.sectionDescription')}
    >
      <FormField
        control={control}
        name="products_of_interest"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('products.ofInterest.fieldLabel')}</FormLabel>
            <FormControl>
              <ProductsOfInterestField
                value={field.value}
                onChange={field.onChange}
                categoryIds={categoryIds}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    </FormSection>
  )
}
