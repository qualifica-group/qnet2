import { useTranslation } from 'react-i18next'
import { Building2, FileSignature } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { WorkOrderAttributesSection } from '@/features/work-orders/work-order-attributes-section'
import { WorkOrderContractDataPanel } from '@/features/work-orders/work-order-detail-contract-data-panel'
import { WorkOrderIdentitySection, WorkOrderInternalNotesRow } from '@/features/work-orders/work-order-record-identity'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-record-team'
import type { WorkOrderDetailEditor } from '@/features/work-orders/use-work-order-inline-edit'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

type LinkedRecord = { id: number; name: string } | null | undefined

/** A linked-record row that falls back to the kit's empty placeholder. */
function LinkedField({ label, domain, record }: { label: string; domain: string; record: LinkedRecord }) {
  return (
    <RecordField label={label}>
      {record ? (
        <RecordLink domain={domain} id={record.id}>
          {record.name}
        </RecordLink>
      ) : (
        <DetailEmpty />
      )}
    </RecordField>
  )
}

interface DetailSectionProps {
  workOrder: WorkOrderDetailWithPermissions
  editor: WorkOrderDetailEditor
}

/**
 * Contratto: the client and the contract are reached through the offer
 * (read-only, server-derived). The contract data have their own full-width
 * section right after (`WorkOrderContractDataPanel`).
 */
function WorkOrderContractSection({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('workOrders.detail.sections.contract')} icon={<FileSignature />}>
      <RecordFieldList>
        <RecordField label={t('workOrders.detail.registry')}>
          {workOrder.registry ? (
            <RecordLink domain="registries" id={workOrder.registry.id} className="font-medium text-primary">
              {workOrder.registry.name}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
        <RecordField label={t('workOrders.detail.contract')}>
          {workOrder.contract ? (
            <RecordLink domain="contracts" id={workOrder.contract.id} className="font-medium text-primary">
              {workOrder.contract.title}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * Societa' e sedi of the linked offer: the same three relations, labels and
 * links the Contract detail shows, projected live through `quote`.
 */
function WorkOrderCompanySection({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()
  const operationalSite = workOrder.operational_site

  return (
    <RecordSection title={t('workOrders.detail.sections.company')} icon={<Building2 />}>
      <RecordFieldList>
        <LinkedField label={t('workOrders.detail.company')} domain="companies" record={workOrder.company} />
        <LinkedField
          label={t('workOrders.detail.companySite')}
          domain="company-sites"
          record={workOrder.company_site}
        />
        <LinkedField
          label={t('workOrders.detail.operationalSite')}
          domain="operational-sites"
          record={operationalSite ? { id: operationalSite.id, name: operationalSite.label } : null}
        />
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * The work order record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0195 applied to Commesse, user directive
 * 2026-10-06): the internal notes callout first, then details, team,
 * contract, company and sites and the collected Attribute values, each
 * Attribute a row of its own. Status and completion are computed (spec
 * 0149): they stay the header pill and the KPI strip. The forced closure is
 * an action of the header (user directive 2026-10-06), its reason a read-only
 * row of the details.
 */
export function WorkOrderDetailSections({ workOrder, editor }: DetailSectionProps) {
  const { inline } = editor

  return (
    <RecordSectionsGrid>
      <WorkOrderInternalNotesRow
        notes={workOrder.internal_notes}
        form={editor}
        inline={inline}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <WorkOrderIdentitySection
        values={{
          title: workOrder.title,
          code: workOrder.code,
          type: workOrder.type,
          start_date: workOrder.start_date,
          callback_date: workOrder.callback_date,
          description: workOrder.description,
          task_template: workOrder.task_template,
          force_close_reason: workOrder.is_force_closed ? workOrder.force_close_reason : null,
        }}
        form={editor}
        inline={inline}
      />

      <WorkOrderTeamSection
        supervisors={workOrder.supervisors}
        participants={workOrder.participants}
        form={editor}
        inline={inline}
      />

      <WorkOrderContractSection workOrder={workOrder} />

      <WorkOrderCompanySection workOrder={workOrder} />

      <WorkOrderContractDataPanel workOrder={workOrder} />

      <WorkOrderAttributesSection
        attributes={workOrder.applicable_attributes}
        layout={workOrder.attribute_layout}
        values={workOrder.attribute_values}
        inline={inline}
        control={editor.form.control}
        className={FULL_WIDTH_SECTION_CLASS}
      />
    </RecordSectionsGrid>
  )
}
