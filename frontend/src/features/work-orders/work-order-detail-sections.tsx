import { useTranslation } from 'react-i18next'
import { Building2 } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordSkeletonSections } from '@/components/detail/record-skeleton-sections'
import {
  ProjectedContactsSection,
  ProjectedContextSection,
  ProjectedDocumentSection,
} from '@/features/quotes/quote-projection-sections'
import { RewardChipsSection } from '@/features/rewards/reward-chips-section'
import { WorkOrderAttributesSection } from '@/features/work-orders/work-order-attributes-section'
import { WorkOrderContractDataPanel } from '@/features/work-orders/work-order-detail-contract-data-panel'
import { WorkOrderIdentitySection, WorkOrderInternalNotesRow } from '@/features/work-orders/work-order-record-identity'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-record-team'
import type { RewardAssignmentRef } from '@/features/rewards/types'
import type { WorkOrderDetailEditor } from '@/features/work-orders/use-work-order-inline-edit'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** Stable empty default: a missing `rewards` key reads the same as `[]`. */
const EMPTY_REWARDS: RewardAssignmentRef[] = []

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
 * 2026-10-06): the internal notes callout first, then the shared record
 * skeleton (spec 0331): Dati commessa (with the link to the Contratto),
 * Contesto, Anagrafica e contatti, Buoni, its own Team, Societa' e sedi,
 * Documento e pagamento and the full-width contract data; the collected
 * Attribute values close the card, each a row of its own. Everything the
 * offer owns is a read-only projection. Status and completion are computed
 * (spec 0149): they stay the header pill and the KPI strip. The forced
 * closure is an action of the header (user directive 2026-10-06), its reason
 * a read-only row of the data.
 */
export function WorkOrderDetailSections({ workOrder, editor }: DetailSectionProps) {
  const { t } = useTranslation()
  const { inline } = editor
  // Spec 0267: a multi-offer work order has no single client/contract/company; each product line names its own client.
  const hasSingleQuote = !workOrder.is_multi_quote

  return (
    <RecordSectionsGrid>
      <WorkOrderInternalNotesRow
        notes={workOrder.internal_notes}
        form={editor}
        inline={inline}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSkeletonSections
        details={
          <WorkOrderIdentitySection
            title={t('workOrders.detail.sections.data')}
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
            workflowStatus={{
              current: workOrder.work_order_workflow_status,
              statuses: workOrder.work_order_workflow_statuses,
            }}
            form={editor}
            inline={inline}
          >
            {hasSingleQuote ? (
              <LinkedField
                label={t('workOrders.detail.contract')}
                domain="contracts"
                record={
                  workOrder.contract
                    ? { id: workOrder.contract.id, name: `${workOrder.contract.code} — ${workOrder.contract.title}` }
                    : null
                }
              />
            ) : null}
          </WorkOrderIdentitySection>
        }
        context={
          hasSingleQuote ? (
            <ProjectedContextSection source={workOrder.source} productLines={workOrder.product_lines} />
          ) : null
        }
        contacts={
          hasSingleQuote ? (
            <ProjectedContactsSection
              registry={workOrder.registry}
              referent={workOrder.referent}
              commercial={workOrder.commercial}
              reporter={workOrder.reporter}
            />
          ) : null
        }
        rewards={
          hasSingleQuote ? (
            <RewardChipsSection
              title={t('workOrders.detail.sections.rewards')}
              rewards={workOrder.rewards ?? EMPTY_REWARDS}
              emptyLabel={t('common.noRewards')}
            />
          ) : null
        }
        team={
          <WorkOrderTeamSection
            title={t('workOrders.detail.sections.detailTeam')}
            supervisors={workOrder.supervisors}
            participants={workOrder.participants}
            form={editor}
            inline={inline}
          />
        }
        company={hasSingleQuote ? <WorkOrderCompanySection workOrder={workOrder} /> : null}
        document={
          hasSingleQuote ? (
            <ProjectedDocumentSection layout={workOrder.layout} paymentMethod={workOrder.payment_method} />
          ) : null
        }
        specific={<WorkOrderContractDataPanel workOrder={workOrder} />}
      />

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
