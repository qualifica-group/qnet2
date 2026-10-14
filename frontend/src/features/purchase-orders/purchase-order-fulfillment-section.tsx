import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PackageCheck, RotateCcw, Lock } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { TooltipProvider } from '@/components/ui/tooltip'
import { PurchaseOrderCloseResidualDialog } from '@/features/purchase-orders/purchase-order-close-residual-dialog'
import {
  PurchaseOrderFulfillmentBadge,
  PurchaseOrderResidualClosedIndicator,
} from '@/features/purchase-orders/purchase-order-fulfillment-badge'
import { PurchaseOrderReceiptDialog } from '@/features/purchase-orders/purchase-order-receipt-dialog'
import { formatQuantity, isReceivable } from '@/features/purchase-orders/purchase-order-receipt-schema'
import type { PurchaseOrder, PurchaseOrderLine } from '@/features/purchase-orders/types'
import { useReopenResidual } from '@/features/purchase-orders/use-purchase-order-fulfillment'

const NUMBER_HEAD = 'px-2 py-1 text-right font-medium'
const NUMBER_CELL = 'px-2 py-1 text-right tabular-nums'

interface LineRowProps {
  line: PurchaseOrderLine
  canReceive: boolean
  isReopening: boolean
  onClose: (line: PurchaseOrderLine) => void
  onReopen: (line: PurchaseOrderLine) => void
}

function FulfillmentRow({ line, canReceive, isReopening, onClose, onReopen }: LineRowProps) {
  const { t } = useTranslation()
  return (
    <tr className="border-t align-middle">
      <td className="px-2 py-1">
        <span className="line-clamp-2">
          {line.position}. {line.description}
        </span>
      </td>
      <td className={NUMBER_CELL}>{formatQuantity(line.quantity)}</td>
      <td className={NUMBER_CELL}>{formatQuantity(line.received_quantity)}</td>
      <td className={NUMBER_CELL}>{formatQuantity(line.invoiced_quantity)}</td>
      <td className={NUMBER_CELL}>{formatQuantity(line.residual_quantity)}</td>
      <td className="px-2 py-1">
        <div className="flex flex-wrap items-center gap-1">
          <PurchaseOrderFulfillmentBadge fulfillment={line.fulfillment} />
          {line.residual_closed ? <PurchaseOrderResidualClosedIndicator closure={line.residual_closed} /> : null}
        </div>
      </td>
      <td className="px-2 py-1 text-right">
        {canReceive && line.fulfillment !== 'complete' ? (
          <Button
            type="button"
            variant="outline"
            size="xs"
            className="bg-card"
            aria-label={t('purchaseOrderActions.residual.closeAction', { position: line.position })}
            onClick={() => onClose(line)}
          >
            <Lock aria-hidden="true" />
            {t('purchaseOrderActions.residual.close')}
          </Button>
        ) : null}
        {canReceive && line.residual_closed ? (
          <Button
            type="button"
            variant="outline"
            size="xs"
            className="bg-card"
            disabled={isReopening}
            aria-label={t('purchaseOrderActions.residual.reopenAction', { position: line.position })}
            onClick={() => onReopen(line)}
          >
            <RotateCcw aria-hidden="true" />
            {t('purchaseOrderActions.residual.reopen')}
          </Button>
        ) : null}
      </td>
    </tr>
  )
}

/**
 * Fulfilment of the ODA lines (spec 0254): ordered / received / invoiced /
 * residual, the line state with the "residuo chiuso" marker, and, under
 * `abilities.receive`, "Registra ricezione" plus close/reopen residual per line.
 * The editable draft form above is untouched; this is the read-only view once
 * the order leaves draft/in_validation.
 */
export function PurchaseOrderFulfillmentSection({ order }: { order: PurchaseOrder }) {
  const { t } = useTranslation()
  const [receiptOpen, setReceiptOpen] = useState(false)
  const [closing, setClosing] = useState<PurchaseOrderLine | null>(null)
  const reopen = useReopenResidual(order.id)
  const canReceive = order.abilities.receive
  const canRegister = canReceive && order.lines.some(isReceivable)

  return (
    <section
      className="flex flex-col gap-3 rounded-lg border bg-card p-3"
      aria-label={t('purchaseOrderActions.fulfillment.section')}
    >
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-base font-semibold">{t('purchaseOrderActions.fulfillment.section')}</h2>
        {canRegister ? (
          <Button type="button" size="xs" onClick={() => setReceiptOpen(true)}>
            <PackageCheck aria-hidden="true" />
            {t('purchaseOrderActions.receipts.register')}
          </Button>
        ) : null}
      </div>
      <TooltipProvider>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[40rem] text-xs">
            <thead className="bg-surface text-muted-foreground">
              <tr>
                <th scope="col" className="px-2 py-1 text-left font-medium">
                  {t('purchaseOrderActions.fulfillment.columns.line')}
                </th>
                <th scope="col" className={NUMBER_HEAD}>{t('purchaseOrderActions.fulfillment.columns.ordered')}</th>
                <th scope="col" className={NUMBER_HEAD}>{t('purchaseOrderActions.fulfillment.columns.received')}</th>
                <th scope="col" className={NUMBER_HEAD}>{t('purchaseOrderActions.fulfillment.columns.invoiced')}</th>
                <th scope="col" className={NUMBER_HEAD}>{t('purchaseOrderActions.fulfillment.columns.residual')}</th>
                <th scope="col" className="px-2 py-1 text-left font-medium">
                  {t('purchaseOrderActions.fulfillment.columns.state')}
                </th>
                <th scope="col" className="px-2 py-1">
                  <span className="sr-only">{t('purchaseOrderActions.fulfillment.columns.actions')}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {order.lines.map((line) => (
                <FulfillmentRow
                  key={line.id}
                  line={line}
                  canReceive={canReceive}
                  isReopening={reopen.isPending}
                  onClose={setClosing}
                  onReopen={(target) => reopen.mutate(target.id)}
                />
              ))}
            </tbody>
          </table>
        </div>
      </TooltipProvider>
      {receiptOpen ? <PurchaseOrderReceiptDialog order={order} onOpenChange={setReceiptOpen} /> : null}
      {closing ? (
        <PurchaseOrderCloseResidualDialog
          orderId={order.id}
          line={closing}
          onOpenChange={(open) => (open ? undefined : setClosing(null))}
        />
      ) : null}
    </section>
  )
}
