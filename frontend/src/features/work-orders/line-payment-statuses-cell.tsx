import type { ICellRendererParams } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { EmptyCell } from '@/features/table/cell-renderers'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'

interface ColoredRef {
  name: string
  color: string
}

/** One typology of the `line_payment_statuses` row value (WorkOrderLinePaymentStatusColumn). */
interface TypologyGroup {
  /** "Consulenza: Saldato, Nessuno stato", the export text; unique per row. */
  name: string
  typology: ColoredRef | null
  /** Distinct statuses of its products, in line order; null = a product without status. */
  statuses: Array<ColoredRef | null>
  products: Array<{ code: string | null; name: string | null; status: string | null }>
}

/** "Nessuno stato": a hollow dot beside the muted label, so it lines up with the coloured statuses. */
function NoStatusLabel() {
  const { t } = useTranslation()

  return (
    <span className="inline-flex items-center gap-1.5 text-muted-foreground">
      <span aria-hidden="true" className="size-2.5 shrink-0 rounded-full border border-muted-foreground/60" />
      {t('workOrders.contractData.payment.noStatus')}
    </span>
  )
}

function GroupHeader({ group }: { group: TypologyGroup }) {
  return group.typology ? <ProductTypologyBadge name={group.typology.name} color={group.typology.color} /> : null
}

/** The tooltip: every product of the commessa with its own status, under its typology. */
function ProductsBreakdown({ groups }: { groups: TypologyGroup[] }) {
  const { t } = useTranslation()

  return (
    <div className="flex max-w-80 flex-col gap-2 text-left">
      {groups.map((group) => (
        <div key={group.name} className="flex flex-col gap-1">
          <GroupHeader group={group} />
          <ul className="flex flex-col gap-0.5">
            {group.products.map((product, index) => (
              <li key={`${product.code}-${index}`} className="flex items-baseline gap-1.5">
                <span className="shrink-0 font-mono text-muted-foreground">{product.code}</span>
                <span className="min-w-0 truncate">{product.name}</span>
                <span className="shrink-0 text-muted-foreground">·</span>
                <span className="shrink-0">{product.status ?? t('workOrders.contractData.payment.noStatus')}</span>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  )
}

/**
 * "Stato pagamento" of the Commesse grid (user directives 2026-11-17): per
 * typology its badge (Consulenza, Ente...) then the statuses of its products,
 * a product without status as "Nessuno stato"; on hover or focus, each
 * product with its own status.
 */
export function LinePaymentStatusesCell({ value }: ICellRendererParams) {
  const groups = Array.isArray(value) ? (value as TypologyGroup[]) : []

  if (groups.length === 0) {
    return <EmptyCell align="left" />
  }

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger asChild>
          <div
            role="group"
            tabIndex={0}
            aria-label={groups.map((group) => group.name).join('; ')}
            className="flex h-full items-center gap-3 overflow-hidden text-xs focus-visible:outline-2 focus-visible:outline-ring"
          >
            {groups.map((group) => (
              <span key={group.name} className="inline-flex shrink-0 items-center gap-1.5">
                <GroupHeader group={group} />
                {group.statuses.map((status) =>
                  status ? <PaymentStatusLabel key={status.name} status={status} /> : <NoStatusLabel key="none" />,
                )}
              </span>
            ))}
          </div>
        </TooltipTrigger>
        <TooltipContent side="bottom" align="start" variant="light">
          <ProductsBreakdown groups={groups} />
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  )
}
