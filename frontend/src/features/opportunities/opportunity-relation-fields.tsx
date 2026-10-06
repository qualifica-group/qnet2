import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Info } from 'lucide-react'
import { useFormContext, useWatch, type Control } from 'react-hook-form'
import { ManagerSlotsField } from '@/components/form/manager-slots-field'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { FIELD_STACK_CLASS } from '@/components/record-form/layout'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import { useProductsOfInterestCoherence } from '@/features/products/use-products-of-interest-coherence'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { OpportunityContactRecap } from '@/features/opportunities/opportunity-contact-recap'
import { useOpportunityManagerLabels } from '@/features/opportunities/use-opportunity-manager-labels'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/*
 * The opportunity's relation fields, one component each, shared by the
 * detail's in-place rows and the create draft's rows (spec 0198). The
 * anagrafica and the Segnalatore keep their own files
 * (`opportunity-registry-field.tsx`, `opportunity-reporter-field.tsx`).
 */

interface FieldProps {
  control: Control<OpportunityFormValues>
}

interface SelectedFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never waits on a label lookup. */
  selected: RelationFieldRef | null
}

/** The shared picker strings of every single relation of the form. */
function useSelectLabels() {
  const { t } = useTranslation()
  return {
    placeholder: t('opportunities.form.selectPlaceholder'),
    emptyLabel: t('opportunities.form.selectEmpty'),
    errorLabel: t('opportunities.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}

/** "Referente": scoped to the anagrafica (BR-4), so the row only opens once one is chosen. */
export function OpportunityReferentField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  const selectLabels = useSelectLabels()
  const [registryId, referentId] = useWatch({ control, name: ['registry_id', 'referent_id'] })

  return (
    <div className={FIELD_STACK_CLASS}>
      <RelationSelectField
        control={control}
        name="referent_id"
        metaKey="referent_id"
        label={t('opportunities.form.referent')}
        resource={REFERENTS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('opportunities.form.referentSearch')}
        selected={selected}
        params={registryId !== null ? { registry_id: registryId } : undefined}
        forceDisabled={registryId === null}
        {...selectLabels}
      />
      <OpportunityContactRecap referentId={referentId} />
    </div>
  )
}

/** "Commerciale": the whole platform list (A-3), with its contacts recap (A-4). */
export function OpportunityCommercialField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  const selectLabels = useSelectLabels()
  const commercialId = useWatch({ control, name: 'commercial_id' })

  return (
    <div className={FIELD_STACK_CLASS}>
      <RelationSelectField
        control={control}
        name="commercial_id"
        metaKey="commercial_id"
        label={t('opportunities.form.commercial')}
        resource={REFERENTS_FOR_SELECT_RESOURCE}
        searchPlaceholder={t('opportunities.form.commercialSearch')}
        selected={selected}
        {...selectLabels}
      />
      <OpportunityContactRecap referentId={commercialId} />
    </div>
  )
}

export function OpportunitySourceField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  const selectLabels = useSelectLabels()
  return (
    <RelationSelectField
      control={control}
      name="source_id"
      metaKey="source_id"
      label={t('opportunities.form.source')}
      resource={SOURCES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('opportunities.form.sourceSearch')}
      selected={selected}
      {...selectLabels}
    />
  )
}

/** "Supervisore": never required (directive 2026-07-21, it derives from the lead's Operatore, which may be empty). */
export function OpportunitySupervisorField({ control, selected }: SelectedFieldProps) {
  const { t } = useTranslation()
  const selectLabels = useSelectLabels()
  return (
    <RelationSelectField
      control={control}
      name="supervisor_id"
      metaKey="supervisor_id"
      label={t('opportunities.form.supervisor')}
      resource={USERS_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('opportunities.form.supervisorSearch')}
      selected={selected}
      showAvatar
      {...selectLabels}
    />
  )
}

/** Converts the wire `manager_labels` (string position keys) to `ManagerSlotsField`'s own `Record<number, string>`. */
function toSlotLabels(source: Record<string, string>): Record<number, string> | undefined {
  const entries = Object.entries(source).map(([position, label]) => [Number(position), label] as const)
  return entries.length > 0 ? Object.fromEntries(entries) : undefined
}

interface ManagersFieldProps extends FieldProps {
  /** `{id, label}` of the filled slots. */
  selected: ForSelectItem[]
  /** Spec 0087 (D-7): the G.A. are kept identical to one of the Offerte's; `false` on a create. */
  synchronized: boolean
}

/**
 * "Gestori account": the shared, ordered "G.A. n" slots (`ManagerSlotsField`).
 * Their labels (spec 0080) are resolved LIVE from the form's own current
 * `product_lines`, so a product-line change re-labels them before any save.
 */
export function OpportunityManagersField({ control, selected, synchronized }: ManagersFieldProps) {
  const { t } = useTranslation()
  const productLines = useWatch({ control, name: 'product_lines' })
  const slotLabels = toSlotLabels(useOpportunityManagerLabels(productLines))

  return (
    <div className="flex flex-col gap-2">
      {synchronized ? (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Info aria-hidden="true" className="size-3.5 shrink-0" />
          {t('opportunities.form.managersSyncHint')}
        </p>
      ) : null}
      <MetaField control={control} name="manager_slots" metaKey="manager_slots" label={t('opportunities.form.managers')}>
        {({ field, disabled }) => (
          <ManagerSlotsField
            value={field.value}
            onChange={field.onChange}
            selectedItems={selected}
            disabled={disabled}
            labels={slotLabels}
          />
        )}
      </MetaField>
    </div>
  )
}

/**
 * "Righe di classificazione" (spec 0132, the shared `ProductLinesField`).
 * Re-pointing or removing a row drops the products of interest that
 * classification no longer covers (user directive 2026-08-05), in the same
 * change — so the operator never reaches the server's refusal
 * (`ProductCategoryCoherence`). Pruning is a user event, not a render effect.
 */
export function OpportunityProductLinesFormField({ control }: FieldProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<OpportunityFormValues>()
  const productsOfInterest = useWatch({ control, name: 'products_of_interest' })
  const keepCoveredProducts = useProductsOfInterestCoherence(productsOfInterest)

  const pruneProductsOfInterest = (rows: ProductLineRow[]) => {
    const kept = keepCoveredProducts(rows)
    if (kept.length !== productsOfInterest.length) {
      setValue('products_of_interest', kept, { shouldDirty: true })
    }
  }

  return (
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
          disabled={disabled}
        />
      )}
    </MetaField>
  )
}

interface ProductsOfInterestFormFieldProps extends FieldProps {
  /** `{id, label}` of the already-selected products, so a badge never falls back to `#id`. */
  selected: ForSelectItem[]
}

/**
 * "Prodotti di interesse" (optional, user directive 2026-09-17): the picker
 * only offers the products of the categories the classification rows hold
 * right now, with no whole-catalogue escape.
 */
export function OpportunityProductsOfInterestFormField({ control, selected }: ProductsOfInterestFormFieldProps) {
  const { t } = useTranslation()
  const productLines = useWatch({ control, name: 'product_lines' })
  const categoryIds = useMemo(
    () => [
      ...new Set(productLines.map((line) => line.product_category_id).filter((id): id is number => id !== null)),
    ],
    [productLines],
  )

  return (
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
          categoryIds={categoryIds}
          selectedItems={selected}
          disabled={disabled}
        />
      )}
    </MetaField>
  )
}
