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
import { RECORD_COLUMN_CLASS } from '@/components/detail/record-layout'
import type { ProductLine } from '@/features/product-lines/types'
import { WorkOrderAttributesSection } from '@/features/work-orders/work-order-attributes-section'
import { WorkOrderClientInfoSection } from '@/features/work-orders/work-order-client-info-section'
import { WorkOrderContractDataPanel } from '@/features/work-orders/work-order-detail-contract-data-panel'
import { WorkOrderDescriptionSections } from '@/features/work-orders/work-order-record-description'
import { WorkOrderAmountRow } from '@/features/work-orders/work-order-amount-row'
import { WorkOrderInternalNotesRow, WorkOrderWorkflowStatusSection } from '@/features/work-orders/work-order-record-identity'
import { WorkOrderForceCloseReasonRow, WorkOrderStartDateRow } from '@/features/work-orders/work-order-record-rows'
import { WorkOrderTeamSection } from '@/features/work-orders/work-order-record-team'
import type { WorkOrderDetailEditor } from '@/features/work-orders/use-work-order-inline-edit'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** Stable empty default: a missing `product_lines` key reads the same as `[]`. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []

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
 * "Riferimenti Società e Funzioni Aziendali" (top of the Info tab's right
 * column): the linked offer's company and sites, the same three relations,
 * labels and links the Contract detail shows, projected live through `quote`,
 * then the business functions of the opportunity's product lines.
 */
function WorkOrderCompanySection({ workOrder }: { workOrder: WorkOrderDetailWithPermissions }) {
  const { t } = useTranslation()
  const operationalSite = workOrder.operational_site
  // The legacy "Funzione Aziendale": in qnet-2 each opportunity product line pairs a business function with a category.
  const businessFunctions = [
    ...new Set((workOrder.product_lines ?? EMPTY_PRODUCT_LINES).map((line) => line.business_function.name)),
  ]

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
        <RecordField label={t('workOrders.detail.businessFunction')}>
          {businessFunctions.length > 0 ? businessFunctions.join(', ') : <DetailEmpty />}
        </RecordField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * The work order's Info tab, laid out as the legacy "Info" tab (user
 * directives 2026-11-16), every user-written field editable in place (spec
 * 0195 applied to Commesse): the internal notes callout first, then two
 * columns. Left: "Info cliente" (spec 0400), "Dati contrattuali commessa"
 * (Importo, Data inizio, the link to the Contratto, the Linee prodotto, then
 * each product with its payment fields (spec 0403) and the totals; no title
 * and no commessa-wide payment status, user directive 2026-11-16), "Stato
 * lavorazione", "Team". Right: "Riferimenti società",
 * "Data richiamo", "Descrizione commessa" (Commessa n., Tipo commessa,
 * Descrizione), then the flexible fields ("Informazioni aggiuntive"). Not on
 * the commessa: Contesto (Fonte), Buoni, Documento e pagamento, Modello di
 * Task. Everything the offer owns is a read-only projection. Status and
 * completion are computed (spec 0149): they stay the header pill and the KPI
 * strip. The forced closure is an action of the header (user directive
 * 2026-10-06), its reason a read-only row of the data.
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

      {/* Own @container per column: the grids inside break on the column's width, not the card's. */}
      <div className={RECORD_COLUMN_CLASS}>
        {hasSingleQuote && workOrder.registry && workOrder.client_info ? (
          <WorkOrderClientInfoSection registry={workOrder.registry} info={workOrder.client_info} />
        ) : null}
        <WorkOrderContractDataPanel workOrder={workOrder}>
          <WorkOrderAmountRow amount={workOrder.amount} />
          <WorkOrderStartDateRow form={editor} inline={inline} startDate={workOrder.start_date} />
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
          <WorkOrderForceCloseReasonRow reason={workOrder.is_force_closed ? workOrder.force_close_reason : null} />
        </WorkOrderContractDataPanel>
        <WorkOrderWorkflowStatusSection
          workflowStatus={{ current: workOrder.work_order_workflow_status, statuses: workOrder.work_order_workflow_statuses }}
          form={editor}
          inline={inline}
        />
        <WorkOrderTeamSection
          title={t('workOrders.detail.sections.detailTeam')}
          supervisors={workOrder.supervisors}
          participants={workOrder.participants}
          form={editor}
          inline={inline}
        />
      </div>
      <div className={RECORD_COLUMN_CLASS}>
        {hasSingleQuote ? <WorkOrderCompanySection workOrder={workOrder} /> : null}
        <WorkOrderDescriptionSections
          values={{
            code: workOrder.code,
            type: workOrder.type,
            callback_date: workOrder.callback_date,
            description: workOrder.description,
          }}
          form={editor}
          inline={inline}
        />
        {workOrder.applicable_attributes.length > 0 ? (
          <WorkOrderAttributesSection
            attributes={workOrder.applicable_attributes}
            layout={workOrder.attribute_layout}
            values={workOrder.attribute_values}
            inline={inline}
            control={editor.form.control}
            collapsible={false}
          />
        ) : null}
      </div>
    </RecordSectionsGrid>
  )
}
