import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { PackageSearch } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { LeadProductOfInterest } from '@/features/leads/types'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

interface LeadProductInterestSectionProps {
  control: Control<LeadFormValues>
  /** `null` when no Campaign is chosen yet — the field stays disabled (AC-040). */
  campaignId: number | null
  /** The chosen Campaign's effective product categories (AC-041). */
  categoryIds: number[]
  /** Already-loaded products (edit mode), so badges never fall back to `#id`. */
  knownProducts: LeadProductOfInterest[]
  className?: string
}

/**
 * The Lead's "Prodotti di interesse" card (spec 0094): the shared
 * `ProductsOfInterestField` (`features/products`), scoped to the chosen
 * Campaign's effective categories and disabled until one is picked. The
 * coherence guard on a Campaign change — highlight the no-longer-covered
 * products and require explicit confirmation, D-5 — lives in
 * `useLeadCampaignProductInterest`; this component only renders.
 */
export function LeadProductInterestSection({
  control,
  campaignId,
  categoryIds,
  knownProducts,
  className,
}: LeadProductInterestSectionProps) {
  const { t } = useTranslation()

  const selectedItems = useMemo<ForSelectItem[]>(
    () =>
      knownProducts.map((product) => ({
        id: product.id,
        label: product.name,
        subtitle: product.product_category?.name ?? null,
      })),
    [knownProducts],
  )

  return (
    <FormSection
      icon={PackageSearch}
      title={t('leads.form.sections.productsOfInterest.title')}
      description={t('leads.form.sections.productsOfInterest.description')}
      className={className}
    >
      <MetaField
        control={control}
        name="products_of_interest"
        metaKey="products_of_interest"
        label={t('products.ofInterest.fieldLabel')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <ProductsOfInterestField
              value={field.value}
              onChange={field.onChange}
              categoryIds={categoryIds}
              selectedItems={selectedItems}
              disabled={disabled || campaignId == null}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
