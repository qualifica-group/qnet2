import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useDocumentRequestSyncLogs } from '@/features/document-requests/use-document-requests'
import { formatDateTime } from '@/features/table/cell-renderers'

const COLUMNS = ['created_at', 'operation', 'outcome', 'http_status', 'attempt', 'message'] as const
const CELL_CLASS = 'px-2 py-1.5 align-top'

interface DocumentRequestSyncLogsDialogProps {
  requestId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Last outbound calls to ArchivioSmart for one request (loaded only while open). */
export function DocumentRequestSyncLogsDialog({ requestId, open, onOpenChange }: DocumentRequestSyncLogsDialogProps) {
  const { t } = useTranslation()
  const logs = useDocumentRequestSyncLogs(requestId, open)

  let body = <Skeleton className="h-24 w-full" />
  if (logs.isError) {
    body = (
      <div role="alert" className="flex items-center gap-2 text-sm text-destructive">
        {t('documentRequests.syncLogs.loadError')}
        <Button type="button" variant="secondary" size="sm" onClick={() => void logs.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  } else if (logs.data && logs.data.length === 0) {
    body = (
      <p className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
        {t('documentRequests.syncLogs.empty')}
      </p>
    )
  } else if (logs.data) {
    body = (
      <div className="overflow-x-auto rounded-md border bg-card">
        <table className="w-full min-w-[32rem] text-left text-xs">
          <caption className="sr-only">{t('documentRequests.syncLogs.description')}</caption>
          <thead className="border-b bg-muted/40 text-muted-foreground">
            <tr>
              {COLUMNS.map((column) => (
                <th key={column} scope="col" className={`${CELL_CLASS} font-medium`}>
                  {t(`documentRequests.syncLogs.columns.${column}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-border/60">
            {logs.data.map((log) => (
              <tr key={log.id}>
                <td className={`${CELL_CLASS} whitespace-nowrap`}>{formatDateTime(log.created_at)}</td>
                <td className={CELL_CLASS}>{t(`documentRequests.syncLogs.operation.${log.operation}`)}</td>
                <td className={CELL_CLASS}>{t(`documentRequests.syncLogs.outcome.${log.outcome}`)}</td>
                <td className={CELL_CLASS}>{log.http_status ?? '—'}</td>
                <td className={CELL_CLASS}>{log.attempt}</td>
                <td className={`${CELL_CLASS} break-words`}>{log.message ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('documentRequests.syncLogs.title')}</DialogTitle>
          <DialogDescription>{t('documentRequests.syncLogs.description')}</DialogDescription>
        </DialogHeader>
        {body}
      </DialogContent>
    </Dialog>
  )
}
