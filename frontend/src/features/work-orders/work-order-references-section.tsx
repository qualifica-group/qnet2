import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import {
  WorkOrderCompanyField,
  WorkOrderCompanySiteField,
  WorkOrderOperationalSiteField,
  WorkOrderProductLinesField,
  WorkOrderRegistryField,
} from '@/features/work-orders/work-order-reference-fields'
import type { WorkOrderFormState } from '@/features/work-orders/use-work-order-form'

/** What the closed rows show: each relation's `{id, name}`, the business functions already rendered. */
export interface WorkOrderReferenceValues {
  registry: RelationFieldRef | null
  company: RelationFieldRef | null
  companySite: RelationFieldRef | null
  operationalSite: RelationFieldRef | null
  productLines: ReactNode
}

interface WorkOrderReferencesSectionProps {
  values: WorkOrderReferenceValues
  form: WorkOrderFormState
  inline: InlineEdit
}

function nameOrEmpty(ref: RelationFieldRef | null): ReactNode {
  return ref !== null ? ref.name : <DetailEmpty />
}

/**
 * "Riferimenti Societa' e Funzioni Aziendali" of a project work order born
 * without an offer (spec 0431): the client, the company and its sites and the
 * business functions are its own, so every row edits in place — on the create
 * draft as on the detail. A work order born from an offer shows the read-only
 * projection of `WorkOrderCompanySection` instead.
 */
export function WorkOrderReferencesSection({ values, form, inline }: WorkOrderReferencesSectionProps) {
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
        <RecordInlineField
          field="product_lines"
          label={t('workOrders.form.references.productLines')}
          inline={inline}
          editor={<WorkOrderProductLinesField control={control} />}
        >
          {values.productLines}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
