import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDateTime } from '@/lib/formatting/date-display'
import { fetchLineStatusLogs, purchaseRequestKeys } from '@/features/purchase-requests/api'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'

interface LineStatusHistoryDialogProps {
  /** Line whose transitions are listed; `null` closes the dialog. */
  lineId: number | null
  onOpenChange: (open: boolean) => void
}

/** Status history of a line (who moved it, from/to, reason), most recent first. */
export function LineStatusHistoryDialog({ lineId, onOpenChange }: LineStatusHistoryDialogProps) {
  const { t } = useTranslation()
  const logs = useQuery({
    queryKey: purchaseRequestKeys.lineLogs(lineId ?? 0),
    queryFn: () => fetchLineStatusLogs(lineId as number),
    enabled: lineId !== null,
  })

  return (
    <Dialog open={lineId !== null} onOpenChange={onOpenChange}>
      <DialogContent size="md">
        <DialogHeader>
          <DialogTitle>{t('purchaseRequests.history.title')}</DialogTitle>
          <DialogDescription>{t('purchaseRequests.history.description')}</DialogDescription>
        </DialogHeader>
        <div className="max-h-[60vh] overflow-y-auto">
          {logs.isPending ? (
            <Skeleton className="h-24 w-full" />
          ) : logs.isError ? (
            <div className="flex flex-col items-start gap-2">
              <p role="alert" className="text-sm text-destructive">
                {t('purchaseRequests.history.loadError')}
              </p>
              <Button variant="outline" size="sm" className="bg-card" onClick={() => void logs.refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : logs.data.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('purchaseRequests.history.empty')}</p>
          ) : (
            <ul className="flex flex-col gap-2">
              {logs.data.map((log) => (
                <li key={log.id} className="flex flex-col gap-1 rounded-md border bg-card p-2 text-xs">
                  <div className="flex flex-wrap items-center gap-2">
                    {log.from_status ? <LineStatusBadge status={log.from_status} /> : null}
                    {log.from_status ? <ArrowRight className="size-3.5 text-muted-foreground" aria-hidden="true" /> : null}
                    <LineStatusBadge status={log.to_status} />
                    {log.is_bulk ? (
                      <Badge variant="outline" className="text-[11px]">
                        {t('purchaseRequests.history.bulk')}
                      </Badge>
                    ) : null}
                  </div>
                  <p className="text-muted-foreground">
                    {log.user.name} - {formatDateTime(log.created_at)}
                  </p>
                  {log.reason ? <p>{log.reason}</p> : null}
                </li>
              ))}
            </ul>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
