import { useTranslation } from 'react-i18next'
import { useMemo } from 'react'
import { useFormContext, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { RelationSelectField, type RelationFieldRef } from '@/components/form/relation-select-field'
import { MetaField } from '@/features/authorization/MetaField'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import { ProductsOfInterestField } from '@/features/products/products-of-interest-field'
import { useProductsOfInterestCoherence } from '@/features/products/use-products-of-interest-coherence'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

/*
 * The own references of a project work order born without an offer (spec
 * 0431): Cliente, Societa', Societa' sede, Sede operativa, Linee prodotto
 * (whose categories give the business functions) and Prodotti, one component
 * each, shared by the create draft and the detail's in-place rows. On any other work order these come from the offer and are
 * never edited here.
 */

interface FieldProps {
  control: Control<WorkOrderFormValues>
}

interface RelationFieldProps extends FieldProps {
  /** The current value's `{id, name}`, so the trigger never falls back to `#id`. */
  selected: RelationFieldRef | null
}

function useRelationLabels() {
  const { t } = useTranslation()
  return {
    placeholder: t('workOrders.form.references.selectPlaceholder'),
    emptyLabel: t('workOrders.form.references.selectEmpty'),
    errorLabel: t('workOrders.form.references.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}

/** "Cliente". */
export function WorkOrderRegistryField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="registry_id"
      metaKey="registry_id"
      label={t('workOrders.form.references.registry')}
      resource={REGISTRIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.references.registrySearch')}
      selected={selected}
      {...useRelationLabels()}
    />
  )
}

/** "Societa'": a new company drops the site of the previous one. */
export function WorkOrderCompanyField({
  control,
  selected,
  setValue,
}: RelationFieldProps & { setValue: UseFormSetValue<WorkOrderFormValues> }) {
  const { t } = useTranslation()
  const companyId = useWatch({ control, name: 'company_id' })
  return (
    <RelationSelectField
      control={control}
      name="company_id"
      metaKey="company_id"
      label={t('workOrders.form.references.company')}
      resource={COMPANIES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.references.companySearch')}
      selected={selected}
      onValueChange={(next) => {
        if (next !== companyId) {
          setValue('company_site_id', null, { shouldDirty: true })
        }
      }}
      {...useRelationLabels()}
    />
  )
}

/** "Societa' sede": scoped to the chosen company, locked without one. */
export function WorkOrderCompanySiteField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  const companyId = useWatch({ control, name: 'company_id' })
  return (
    <RelationSelectField
      control={control}
      name="company_site_id"
      metaKey="company_site_id"
      label={t('workOrders.form.references.companySite')}
      resource={COMPANY_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.references.companySiteSearch')}
      selected={selected}
      forceDisabled={companyId === null}
      params={companyId !== null ? { company_id: companyId } : undefined}
      {...useRelationLabels()}
    />
  )
}

/** "Sede operativa". */
export function WorkOrderOperationalSiteField({ control, selected }: RelationFieldProps) {
  const { t } = useTranslation()
  return (
    <RelationSelectField
      control={control}
      name="operational_site_id"
      metaKey="operational_site_id"
      label={t('workOrders.form.references.operationalSite')}
      resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
      searchPlaceholder={t('workOrders.form.references.operationalSiteSearch')}
      selected={selected}
      {...useRelationLabels()}
    />
  )
}

/**
 * "Linee prodotto" (D-18): the shared root-category-then-category row editor
 * Gestione Richieste and the opportunities use; the business function is
 * read from each chosen category. Re-pointing or removing a line drops the
 * products it was covering, in the same change (`useProductsOfInterestCoherence`),
 * so the operator never reaches the server's refusal.
 */
export function WorkOrderProductLinesField({ control }: FieldProps) {
  const { t } = useTranslation()
  const { setValue } = useFormContext<WorkOrderFormValues>()
  const productIds = useWatch({ control, name: 'product_ids' })
  const keepCoveredProducts = useProductsOfInterestCoherence(productIds)

  const pruneProducts = (rows: ProductLineRow[]) => {
    const kept = keepCoveredProducts(rows)
    if (kept.length !== productIds.length) {
      setValue('product_ids', kept, { shouldDirty: true })
    }
  }

  return (
    <MetaField
      control={control}
      name="product_lines"
      metaKey="product_lines"
      label={t('workOrders.form.references.productLines')}
    >
      {({ field, disabled }) => (
        <ProductLinesField
          value={field.value}
          onChange={(rows) => {
            field.onChange(rows)
            pruneProducts(rows)
          }}
          disabled={disabled}
          area="opportunities"
        />
      )}
    </MetaField>
  )
}

/**
 * "Prodotti" (D-17/D-18): the products in place of the offer lines, several
 * and optional, picked only among the categories of the product lines held
 * right now (locked until one is chosen); their categories bring the flexible
 * fields. Only active products are offered (the for-select filters them).
 */
export function WorkOrderProductsField({ control, selected }: FieldProps & { selected: ForSelectItem[] }) {
  const { t } = useTranslation()
  const productLines = useWatch({ control, name: 'product_lines' })
  const categoryIds = useMemo(
    () => productLines.flatMap((row) => (row.product_category_id !== null ? [row.product_category_id] : [])),
    [productLines],
  )

  return (
    <MetaField control={control} name="product_ids" metaKey="product_ids" label={t('workOrders.form.references.products')}>
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
