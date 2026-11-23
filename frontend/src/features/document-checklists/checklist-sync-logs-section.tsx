import { useTranslation } from 'react-i18next'
import { useQuery, type QueryKey } from '@tanstack/react-query'
import { TriangleAlert } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ChecklistSyncStatusBadge } from '@/features/document-checklists/checklist-sync-status'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { DocumentChecklistSyncLog, DocumentChecklistSyncStatus } from '@/features/document-checklists/types'

const SYNC_LOGS_SHOWN = 20
const LOG_SKELETON_ROWS = 3

/** Sync fields shared by checklists and categories (spec 0410: the same section serves both). */
interface SyncedRecord {
  sync_status: DocumentChecklistSyncStatus
  sync_error: string | null
  synced_at: string | null
}

interface ChecklistSyncLogsSectionProps {
  record: SyncedRecord
  logsQueryKey: QueryKey
  fetchLogs: () => Promise<DocumentChecklistSyncLog[]>
}

const CELL_CLASS = 'px-2 py-1.5 align-top'

function SyncLogsTable({ logs }: { logs: DocumentChecklistSyncLog[] }) {
  const { t } = useTranslation()

  return (
    <div className="overflow-x-auto rounded-md border bg-card">
      <table className="w-full min-w-[32rem] text-left text-xs">
        <caption className="sr-only">{t('documentChecklists.syncLogs.description')}</caption>
        <thead className="border-b bg-muted/40 text-muted-foreground">
          <tr>
            {(['created_at', 'operation', 'outcome', 'http_status', 'message'] as const).map((column) => (
              <th key={column} scope="col" className={`${CELL_CLASS} font-medium`}>
                {t(`documentChecklists.syncLogs.columns.${column}`)}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-border/60">
          {logs.map((log) => (
            <tr key={log.id}>
              <td className={`${CELL_CLASS} whitespace-nowrap`}>{formatDateTime(log.created_at)}</td>
              <td className={CELL_CLASS}>{t(`documentChecklists.syncLogs.operation.${log.operation}`)}</td>
              <td className={CELL_CLASS}>{t(`documentChecklists.syncLogs.outcome.${log.outcome}`)}</td>
              <td className={CELL_CLASS}>{log.http_status ?? '—'}</td>
              <td className={`${CELL_CLASS} break-words`}>{log.message ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

/**
 * "Sincronizzazioni" block of a detail (spec 0403 AC-027, generic since spec
 * 0410): the current sync state (+ last error) and the latest outbound calls,
 * fetched through the caller's fetcher.
 */
export function ChecklistSyncLogsSection({ record, logsQueryKey, fetchLogs }: ChecklistSyncLogsSectionProps) {
  const { t } = useTranslation()
  const logsQuery = useQuery({
    queryKey: logsQueryKey,
    queryFn: fetchLogs,
  })
  const logs = logsQuery.data?.slice(0, SYNC_LOGS_SHOWN) ?? []
  const syncedAt = formatDateTime(record.synced_at)

  return (
    <RecordSection title={t('documentChecklists.syncLogs.title')} full>
      <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
        <ChecklistSyncStatusBadge status={record.sync_status} />
        {syncedAt ? (
          <span>
            {t('documentChecklists.detail.syncedAt')}: {syncedAt}
          </span>
        ) : null}
      </div>
      {record.sync_error ? (
        <p
          role="alert"
          className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          <span>
            <span className="font-medium">{t('documentChecklists.detail.syncError')}:</span> {record.sync_error}
          </span>
        </p>
      ) : null}
      {logsQuery.isError ? (
        <div className="flex items-center gap-2 text-sm text-destructive" role="alert">
          {t('documentChecklists.syncLogs.loadError')}
          <Button type="button" variant="secondary" size="sm" onClick={() => void logsQuery.refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : logsQuery.isPending ? (
        <div className="flex flex-col gap-1.5" aria-hidden="true">
          {Array.from({ length: LOG_SKELETON_ROWS }, (_, index) => (
            <Skeleton key={index} className="h-6 w-full" />
          ))}
        </div>
      ) : logs.length === 0 ? (
        <p className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
          {t('documentChecklists.syncLogs.empty')}
        </p>
      ) : (
        <SyncLogsTable logs={logs} />
      )}
    </RecordSection>
  )
}
