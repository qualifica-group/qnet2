import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Boxes, PackageSearch } from 'lucide-react'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import { useProductsOfInterestCoherence } from '@/features/products/use-products-of-interest-coherence'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityProductLine, OpportunityProductOfInterest } from '@/features/opportunities/types'

interface OpportunityProductLinesSectionProps {
  control: Control<OpportunityFormValues>
  /** Product-line rows whose labels are already known without a fetch (edit load, from-lead prefill, in-form Lead picker). */
  knownProductLines: OpportunityProductLine[]
  /** Products already on the loaded opportunity (edit), for badge-label hydration. */
  knownProductsOfInterest: OpportunityProductOfInterest[]
}

/**
 * The opportunity's classification axis, as the TWO cards the Gestione
 * Richieste screens split it into (user directive 2026-08-05): the
 * function+category rows (spec 0040 amendment rev.3, AC-106), then the
 * products of interest they scope. Wrapped in `MetaField` (mirrors `manager_slots` in
 * `OpportunityTeamSection`) so a future server-driven field permission and the
 * row-completeness error (`superRefine` in `opportunity-schema.ts`) both
 * surface through the same mechanism as every other field. The row editor
 * itself (spec 0057) is the shared `ProductLinesField`, also consumed by the
 * request-management create form.
 *
 * Owning BOTH fields is what lets it apply the coherence rule the way the
 * Gestione Richieste screens do (user directive 2026-08-05): the picker never
 * leaves the categories chosen above, and re-pointing or removing a row drops
 * the products that classification no longer covers — so the operator never
 * reaches the server's refusal (`ProductCategoryCoherence`).
 */
export function OpportunityProductLinesSection({
  control,
  knownProductLines,
  knownProductsOfInterest,
}: OpportunityProductLinesSectionProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<OpportunityFormValues>()

  // The products picker is scoped by the categories currently chosen in the
  // rows above, so editing a row immediately re-scopes it.
  const productLines = useWatch({ control, name: 'product_lines' })
  const selectedCategoryIds = useMemo(
    () => [
      ...new Set(
        productLines
          .map((line) => line.product_category_id)
          .filter((id): id is number => id !== null),
      ),
    ],
    [productLines],
  )

  // Pruning is a USER EVENT on the rows, not a render-time effect: it belongs
  // to the field's own `onChange` handler (same shape as the work panel).
  const productsOfInterest = useWatch({ control, name: 'products_of_interest' })
  const keepCoveredProducts = useProductsOfInterestCoherence(productsOfInterest)

  const pruneProductsOfInterest = (rows: ProductLineRow[]) => {
    const kept = keepCoveredProducts(rows)

    if (kept.length !== productsOfInterest.length) {
      setValue('products_of_interest', kept, { shouldDirty: true })
    }
  }

  const knownProducts = useMemo<ForSelectItem[]>(
    () =>
      knownProductsOfInterest.map((product) => ({
        id: product.id,
        label: product.name,
        subtitle: product.product_category?.name ?? null,
      })),
    [knownProductsOfInterest],
  )

  return (
    <>
      <FormSection
        icon={Boxes}
        title={t('opportunities.form.sections.productLines.title')}
        description={t('opportunities.form.sections.productLines.description')}
      >
        <MetaField
          control={control}
          name="product_lines"
          metaKey="product_lines"
          label={t('opportunities.form.productLines.fieldLabel')}
        >
          {({ field, disabled }) => (
            <ProductLinesField
              value={field.value}
              onChange={(rows) => {
                field.onChange(rows)
                pruneProductsOfInterest(rows)
              }}
              knownLines={knownProductLines}
              disabled={disabled}
            />
          )}
        </MetaField>
      </FormSection>

      {/* Its own card right after the rows that scope it, exactly as in the
          Gestione Richieste screens (user directive 2026-08-05): the picker
          only offers the products of the categories edited above it, with no
          whole-catalogue escape — the server refuses what falls outside. */}
      <FormSection
        icon={PackageSearch}
        title={t('products.ofInterest.sectionTitle')}
        description={t('products.ofInterest.sectionDescription')}
      >
        <MetaField
          control={control}
          name="products_of_interest"
          metaKey="products_of_interest"
          label={t('products.ofInterest.fieldLabel')}
        >
          {({ field, disabled }) => (
            <ProductsOfInterestField
              value={field.value}
              onChange={field.onChange}
              categoryIds={selectedCategoryIds}
              selectedItems={knownProducts}
              disabled={disabled}
            />
          )}
        </MetaField>
      </FormSection>
    </>
  )
}
