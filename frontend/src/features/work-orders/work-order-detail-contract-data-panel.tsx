import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { BookUser, FileText } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { useAbilities } from '@/features/auth/use-abilities'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import type { ProductLine } from '@/features/product-lines/types'
import { PaymentStatusBadge, PaymentStatusFieldLabel } from '@/features/work-order-contract-data/contract-data-cells'
import { ContractDataLineBox } from '@/features/work-order-contract-data/contract-data-line-box'
import { WorkOrderContractDataSection } from '@/features/work-order-contract-data/work-order-contract-data-section'
import type { WorkOrderDetailWithPermissions, WorkOrderQuoteLine } from '@/features/work-orders/types'

/**
 * The commessa's products for an actor who may not see the money (spec 0403
 * D-6): per product only Titolo and Stato pagamento, read-only, ordered like
 * the picker. On a multi-offer work order (spec 0267) each line names its own client.
 */
function WorkOrderLinesList({ lines, showRegistry }: { lines: WorkOrderQuoteLine[]; showRegistry: boolean }) {
  const { t } = useTranslation()

  if (lines.length === 0) {
    return <DetailEmpty />
  }

  const sorted = [...lines].sort((a, b) => a.sort_order - b.sort_order)

  return (
    <div className="flex flex-col gap-2">
      {sorted.map((line) => (
        <ContractDataLineBox key={line.id} label={line.product ? `${line.product.code} ${line.product.name}` : undefined}>
          <RecordFieldList>
            <RecordField label={t('workOrders.contractData.fields.title')}>
              <span className="flex min-w-0 flex-wrap items-baseline gap-x-2">
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
                {showRegistry && line.registry ? (
                  <span className="inline-flex min-w-0 items-center gap-1 text-xs">
                    <BookUser className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <RecordLink domain="registries" id={line.registry.id} className="min-w-0 text-primary">
                      {line.registry.name}
                    </RecordLink>
                  </span>
                ) : null}
              </span>
            </RecordField>
            <RecordField label={<PaymentStatusFieldLabel typology={line.typology ?? null} />}>
              <PaymentStatusBadge status={line.payment_status ?? null} />
            </RecordField>
          </RecordFieldList>
        </ContractDataLineBox>
      ))}
    </div>
  )
}

/** Stable empty default: a missing `product_lines` key reads the same as `[]`. */
const EMPTY_PRODUCT_LINES: ProductLine[] = []

interface WorkOrderContractDataPanelProps {
  workOrder: WorkOrderDetailWithPermissions
  /** The work order's own data rows (Titolo, Data inizio, Contratto...) opening the section. */
  children?: ReactNode
}

/**
 * "Dati contrattuali commessa", the Info tab's main left section (user
 * directives 2026-11-16, "Dati commessa" merged in): the work order's own data
 * rows first, then the opportunity's "Linee prodotto" (not money: shown
 * whenever the work order has a single offer), then the products (spec
 * 0403): with `view_costs` and `view_contract_data` each product with
 * Titolo, Importo, Stato pagamento, Documento, Accordo and Insoluti, then the
 * totals; otherwise only Titolo and Stato pagamento of each product, no
 * money (user directive 2026-11-16, supersedes spec 0380's "nothing without
 * view_costs"). The lines themselves are not editable on a work order: they
 * are chosen when it is created.
 */
export function WorkOrderContractDataPanel({ workOrder, children }: WorkOrderContractDataPanelProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const { actions } = workOrder.permissions
  const hasProductLines = !workOrder.is_multi_quote

  return (
    <RecordSection title={t('workOrders.contractData.title')} icon={<FileText />} full>
      <RecordFieldList>
        {children}
        {hasProductLines ? (
          <RecordField label={t('quotes.detail.productLines')}>
            <ProductLinesReadOnlyList lines={workOrder.product_lines ?? EMPTY_PRODUCT_LINES} />
          </RecordField>
        ) : null}
      </RecordFieldList>
      {actions.view_costs && actions.view_contract_data ? (
        <WorkOrderContractDataSection
          workOrderId={workOrder.id}
          canManage={actions.manage_payments === true}
          showRegistry={workOrder.is_multi_quote}
          canOpenDocuments={can('invoices.view')}
        />
      ) : (
        <WorkOrderLinesList lines={workOrder.quote_lines} showRegistry={workOrder.is_multi_quote} />
      )}
    </RecordSection>
  )
}
