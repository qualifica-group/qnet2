import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { History } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { fetchMergeLogs, purchaseOrderKeys } from '@/features/purchase-orders/api'
import type { MergeLog, PurchaseOrder } from '@/features/purchase-orders/types'

function LogEntry({ log }: { log: MergeLog }) {
  const { t } = useTranslation()
  const movedTotal = log.moved_lines.reduce((sum, line) => sum + Number(line.total_amount), 0)
  return (
    <li className="flex flex-col gap-2 rounded-lg border bg-card p-3 text-xs">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <span className="text-sm font-semibold">{log.source_code}</span>
        <Badge variant={log.source_action === 'deleted' ? 'destructive' : 'secondary'} className="text-[11px]">
          {t(`purchaseOrderActions.history.actions.${log.source_action}`)}
        </Badge>
        <span className="text-muted-foreground">{formatDate(log.source_order_date)}</span>
        <span className="ml-auto text-muted-foreground">
          {formatDateTime(log.created_at)} {t('purchaseOrderActions.history.by', { name: log.performed_by.name })}
        </span>
      </div>
      <p className="truncate" title={log.source_description}>
        {log.source_description}
      </p>
      <table className="w-full">
        <caption className="sr-only">{t('purchaseOrderActions.history.movedLines')}</caption>
        <tbody className="divide-y rounded-md border bg-surface">
          {log.moved_lines.map((line, index) => (
            <tr key={`${line.description}-${index}`}>
              <td className="max-w-0 truncate px-2 py-1" title={line.description}>
                {line.description}
              </td>
              <td className="px-2 py-1 text-right tabular-nums text-muted-foreground">
                {line.quantity} x {line.unit_price}
              </td>
              <td className="px-2 py-1 text-right tabular-nums">{formatQuoteAmount(Number(line.total_amount))}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="font-medium">
            <th scope="row" colSpan={2} className="px-2 py-1 text-right">
              {t('purchaseOrderActions.history.movedTotal')}
            </th>
            <td className="px-2 py-1 text-right tabular-nums">{formatQuoteAmount(movedTotal)}</td>
          </tr>
        </tfoot>
      </table>
    </li>
  )
}

interface PurchaseOrderMergeLogsDialogProps {
  order: PurchaseOrder
  onOpenChange: (open: boolean) => void
}

/** Merge history of an ODA, most recent first (the server orders it). Mounted only while open. */
export function PurchaseOrderMergeLogsDialog({ order, onOpenChange }: PurchaseOrderMergeLogsDialogProps) {
  const { t } = useTranslation()
  const logs = useQuery<MergeLog[], AxiosError>({
    queryKey: purchaseOrderKeys.mergeLogs(order.id),
    queryFn: () => fetchMergeLogs(order.id),
  })
  const list = logs.data ?? []

  return (
    <Dialog open onOpenChange={onOpenChange}>
      <DialogContent size="lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <History className="size-4" aria-hidden="true" />
            {t('purchaseOrderActions.history.title')}
          </DialogTitle>
          <DialogDescription>{t('purchaseOrderActions.history.subtitle', { code: order.code })}</DialogDescription>
        </DialogHeader>
        <div className="flex max-h-[70vh] flex-col gap-2 overflow-y-auto">
          {logs.isLoading ? (
            <Skeleton className="h-24 w-full" />
          ) : logs.isError ? (
            <p role="alert" className="text-sm text-destructive">
              {t('purchaseOrderActions.history.loadError')}
            </p>
          ) : list.length === 0 ? (
            <p role="status" className="text-xs text-muted-foreground">
              {t('purchaseOrderActions.history.empty')}
            </p>
          ) : (
            <ul className="flex flex-col gap-2">
              {list.map((log) => (
                <LogEntry key={log.id} log={log} />
              ))}
            </ul>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
