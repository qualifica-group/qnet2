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
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { quoteLineToForSelectItem } from '@/features/work-orders/quote-line-label'
import {
  WorkOrderAttributesSection,
  WorkOrderNewAttributesFields,
} from '@/features/work-orders/work-order-attributes-section'
import { WorkOrderIdentitySection, WorkOrderInternalNotesRow } from '@/features/work-orders/work-order-record-identity'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-record-team'
import { WorkOrderQuoteLinesFormField } from '@/features/work-orders/work-order-relation-fields'
import type { WorkOrderDetailEditor } from '@/features/work-orders/use-work-order-inline-edit'
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

interface DetailSectionProps {
  workOrder: WorkOrderDetailWithPermissions
  editor: WorkOrderDetailEditor
}

/**
 * Contratto: the client and the contract are reached through the offer
 * (read-only, server-derived); the offer's lines edit in place, together with
 * the Attributes a new line brings in.
 */
function WorkOrderContractSection({ workOrder, editor }: DetailSectionProps) {
  const { t } = useTranslation()
  const { form, inline, attributeContext } = editor
  const persistedCodes = new Set(workOrder.applicable_attributes.map((attribute) => attribute.code))

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
        <RecordInlineField
          field="quote_line_ids"
          label={t('workOrders.detail.lines')}
          inline={inline}
          editor={
            <>
              <WorkOrderQuoteLinesFormField
                control={form.control}
                exceptWorkOrderId={workOrder.id}
                selectedItems={workOrder.quote_lines.map(quoteLineToForSelectItem)}
              />
              <WorkOrderNewAttributesFields
                attributes={attributeContext.applicable_attributes}
                persistedCodes={persistedCodes}
                control={form.control}
              />
            </>
          }
        >
          <WorkOrderLinesList lines={workOrder.quote_lines} />
        </RecordInlineField>
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

      <WorkOrderContractSection workOrder={workOrder} editor={editor} />

      <WorkOrderCompanySection workOrder={workOrder} />

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
