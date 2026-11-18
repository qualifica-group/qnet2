import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import { useOfferLinesCoherence } from '@/features/request-management/use-offer-lines-coherence'

interface RequestProductLinesSectionProps {
  control: Control<RequestWorkFormValues>
  /** Spec 0411: the parent category the request is bound to; its rows may only pick under it. */
  rootCategory?: { id: number; name: string } | null
}

/**
 * "Categoria genitore" + "categoria prodotto" of an existing request (user
 * directive 2026-07-31, spec 0132): the commercials change them from the work
 * panel, not only while creating the request. Rendered with the SAME
 * `ProductLinesField` the create form and the opportunity form use, wrapped in
 * `MetaField` like every other field here so its gating comes from the
 * server-derived permissions. No `knownLines` (spec 0132): every label
 * resolves off the cached category tree, not a persisted-row projection.
 * Re-pointing or removing a row empties the offer rows whose product that
 * classification no longer covers (`useOfferLinesCoherence`).
 */
export function RequestProductLinesSection({ control, rootCategory = null }: RequestProductLinesSectionProps) {
  const { t } = useTranslation()
  const resetUncoveredOfferRows = useOfferLinesCoherence(control)

  return (
    <FormSection
      icon={Boxes}
      title={t('requestManagement.workPanel.productLines.title')}
      description={t('requestManagement.workPanel.productLines.description')}
    >
      <MetaField
        control={control}
        name="product_lines"
        metaKey="product_lines"
        label={t('requestManagement.workPanel.productLines.fieldLabel')}
      >
        {({ field, disabled }) => (
          <ProductLinesField
            value={field.value}
            onChange={(rows) => {
              field.onChange(rows)
              resetUncoveredOfferRows(rows)
            }}
            disabled={disabled}
            area="requests"
            lockedRootCategoryId={rootCategory === null ? null : rootCategory.id}
            extraHints={[
              t('requestManagement.workPanel.productLines.hint'),
              ...(rootCategory === null
                ? []
                : [t('requestManagement.workPanel.productLines.rootLocked', { root: rootCategory.name })]),
            ]}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
