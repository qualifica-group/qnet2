import { useTranslation } from 'react-i18next'
import { Building2, ClipboardList, FileSignature, Lock } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { WorkOrderDetailAttributesSection } from '@/features/work-orders/work-order-detail-attributes'
import { WorkOrderDetailTeam } from '@/features/work-orders/work-order-detail-team'
import type { WorkOrderDetailWithPermissions, WorkOrderQuoteLine } from '@/features/work-orders/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/**
 * Read-only list of the linked offer's REVENUE lines (D-6/D-7), ordered like
 * the picker, in the same plain-list idiom the Opportunita' record uses for its
 * products of interest.
 */
function WorkOrderLinesList({ lines }: { lines: WorkOrderQuoteLine[] }) {
  if (lines.length === 0) {
    return <DetailEmpty />
  }

  const sorted = [...lines].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <ul className="flex flex-col gap-1">
      {sorted.map((line) => (
        <li key={line.id} className="flex min-w-0 items-baseline gap-2">
          {line.product ? (
            <>
              <span className="shrink-0 font-mono text-xs text-muted-foreground">{line.product.code}</span>
              <RecordLink domain="products" id={line.product.id} className="min-w-0 font-medium">
                {line.product.name}
              </RecordLink>
            </>
          ) : (
            <DetailEmpty />
          )}
        </li>
      ))}
    </ul>
  )
}

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
 * The work order record's `RecordSectionsGrid` body, mirroring
 * `OpportunityDetailSections`/`QuoteDetailSections`: the internal notes
 * callout first, then details, team, contract, company and sites and the collected Attribute
 * values. Status, type and dates are not repeated here: they are the header
 * pills and the KPI strip.
 */
export function WorkOrderDetailSections({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      <GeneralNotesCallout
        title={t('workOrders.detail.internalNotes')}
        notes={workOrder.internal_notes}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSection title={t('workOrders.detail.sections.identity')} icon={<ClipboardList />}>
        <RecordFieldList>
          <RecordField label={t('workOrders.detail.description')}>
            {workOrder.description ? (
              // Capped and scrollable, same as the Offerta's inherited notes:
              // a long description must not push the rest of the section away.
              <p className="max-h-40 overflow-y-auto break-words whitespace-pre-wrap">{workOrder.description}</p>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
          <RecordField label={t('workOrders.detail.taskTemplate')}>
            {workOrder.task_template ? (
              <RecordLink domain="task-templates" id={workOrder.task_template.id}>
                {workOrder.task_template.name}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
          {workOrder.is_force_closed ? (
            <RecordField label={t('workOrders.detail.forceCloseReason')} icon={<Lock />}>
              <span className="whitespace-pre-wrap">{workOrder.force_close_reason}</span>
            </RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>

      <WorkOrderDetailTeam supervisors={workOrder.supervisors} participants={workOrder.participants} />

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
          <RecordField label={t('workOrders.detail.lines')}>
            <WorkOrderLinesList lines={workOrder.quote_lines} />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <WorkOrderCompanySection workOrder={workOrder} />

      <WorkOrderDetailAttributesSection
        attributes={workOrder.applicable_attributes}
        values={workOrder.attribute_values}
        className={FULL_WIDTH_SECTION_CLASS}
      />
    </RecordSectionsGrid>
  )
}
