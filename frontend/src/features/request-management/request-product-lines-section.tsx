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
  /** Spec 0432: the parent categories a legacy request is locked to; `[]` when it is not one. */
  legacyRootCategories?: readonly RootCategoryRef[]
}

interface RootCategoryRef {
  id: number
  name: string
}

/** Hoisted: a fresh `[]` default per render would be a new reference each time. */
const NO_ROOTS: readonly RootCategoryRef[] = []

/** The roots the rows may pick and the hint explaining why; `null` roots = every root. */
function useRootLock(rootCategory: RootCategoryRef | null, legacyRootCategories: readonly RootCategoryRef[]) {
  const { t } = useTranslation()
  if (rootCategory !== null) {
    return { rootIds: [rootCategory.id], hint: t('requestManagement.workPanel.productLines.rootLocked', { root: rootCategory.name }) }
  }
  if (legacyRootCategories.length > 0) {
    return {
      rootIds: legacyRootCategories.map((root) => root.id),
      hint: t('requestManagement.workPanel.productLines.legacyRootsLocked', {
        roots: legacyRootCategories.map((root) => root.name).join(', '),
      }),
    }
  }
  return { rootIds: null, hint: null }
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
export function RequestProductLinesSection({
  control,
  rootCategory = null,
  legacyRootCategories = NO_ROOTS,
}: RequestProductLinesSectionProps) {
  const { t } = useTranslation()
  const resetUncoveredOfferRows = useOfferLinesCoherence(control)
  const rootLock = useRootLock(rootCategory, legacyRootCategories)

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
            lockedRootCategoryIds={rootLock.rootIds}
            extraHints={[t('requestManagement.workPanel.productLines.hint'), ...(rootLock.hint === null ? [] : [rootLock.hint])]}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
