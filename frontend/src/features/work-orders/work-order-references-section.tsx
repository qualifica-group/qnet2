import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Boxes, Building2 } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import {
  WorkOrderCompanyField,
  WorkOrderCompanySiteField,
  WorkOrderOperationalSiteField,
  WorkOrderProductLinesField,
  WorkOrderProductsField,
  WorkOrderRegistryField,
} from '@/features/work-orders/work-order-reference-fields'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'

/** What the closed rows show: each relation's `{id, name}`. */
export interface WorkOrderReferenceValues {
  registry: RelationFieldRef | null
  company: RelationFieldRef | null
  companySite: RelationFieldRef | null
  operationalSite: RelationFieldRef | null
  /** D-18: the business functions read from the product lines' categories (read-only). */
  businessFunctions: ReactNode
}

/** The product side: the lines already rendered, the products as `{id, name}`. */
export interface WorkOrderProductValues {
  productLines: ReactNode
  /** D-17: the chosen products, in place of offer lines. */
  products: RelationFieldRef[]
}

interface SectionProps<TValues> {
  values: TValues
  form: WorkOrderFormState
  inline: InlineEdit
}

function nameOrEmpty(ref: RelationFieldRef | null): ReactNode {
  return ref !== null ? ref.name : <DetailEmpty />
}

/**
 * "Riferimenti Societa' e Funzioni Aziendali" of a project work order born
 * without an offer (spec 0431): the client, the company and its sites are its
 * own, so every row edits in place; the business function, read from the
 * product lines, sits under the operational site, read-only — on the create
 * draft as on the detail. A work order born from an offer shows the read-only
 * projection of `WorkOrderCompanySection` instead.
 */
export function WorkOrderReferencesSection({ values, form, inline }: SectionProps<WorkOrderReferenceValues>) {
  const { t } = useTranslation()
  const { control, setValue } = form.form

  return (
    <RecordSection title={t('workOrders.detail.sections.company')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="registry_id"
          label={t('workOrders.form.references.registry')}
          inline={inline}
          editor={<WorkOrderRegistryField control={control} selected={values.registry} />}
        >
          {nameOrEmpty(values.registry)}
        </RecordInlineField>
        <RecordInlineField
          field="company_id"
          label={t('workOrders.detail.company')}
          inline={inline}
          editor={<WorkOrderCompanyField control={control} selected={values.company} setValue={setValue} />}
        >
          {nameOrEmpty(values.company)}
        </RecordInlineField>
        <RecordInlineField
          field="company_site_id"
          label={t('workOrders.detail.companySite')}
          inline={inline}
          editor={<WorkOrderCompanySiteField control={control} selected={values.companySite} />}
        >
          {nameOrEmpty(values.companySite)}
        </RecordInlineField>
        <RecordInlineField
          field="operational_site_id"
          label={t('workOrders.detail.operationalSite')}
          inline={inline}
          editor={<WorkOrderOperationalSiteField control={control} selected={values.operationalSite} />}
        >
          {nameOrEmpty(values.operationalSite)}
        </RecordInlineField>
        {/* User directive 2026-11-25: under the operational site, as on a work order from an offer. */}
        <RecordField label={t('workOrders.detail.businessFunction')}>{values.businessFunctions}</RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Linee prodotto e prodotti" of a project work order born without an offer
 * (spec 0431, D-18, user directive 2026-11-25: a section of its own, before
 * "Dati contrattuali commessa"): the product lines first, then the products
 * picked within them — what the offer lines are on any other work order.
 */
export function WorkOrderProductsSection({ values, form, inline }: SectionProps<WorkOrderProductValues>) {
  const { t } = useTranslation()
  const { control } = form.form
  // The picker's badges read `{id, label}`; memoized so its selection never re-resolves per render.
  const selectedProducts = useMemo(
    () => values.products.map((product) => ({ id: product.id, label: product.name })),
    [values.products],
  )

  return (
    <RecordSection title={t('workOrders.detail.sections.products')} icon={<Boxes />}>
      <RecordFieldList>
        <RecordInlineField
          field="product_lines"
          label={t('workOrders.form.references.productLines')}
          inline={inline}
          editor={<WorkOrderProductLinesField control={control} />}
        >
          {values.productLines}
        </RecordInlineField>
        <RecordInlineField
          field="product_ids"
          label={t('workOrders.form.references.products')}
          inline={inline}
          editor={<WorkOrderProductsField control={control} selected={selectedProducts} />}
        >
          {values.products.length > 0 ? (
            <ul className="flex flex-col gap-1">
              {values.products.map((product) => (
                <li key={product.id} className="min-w-0 font-medium">
                  {product.name}
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
