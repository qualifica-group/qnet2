import { useTranslation } from 'react-i18next'
import { AlertCircle, Loader2, MailOpen, Paperclip } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDateTime } from '@/lib/formatting/date-display'
import { useWorkOrderEmailsList } from '@/features/work-order-emails/use-work-order-emails-list'
import { WorkOrderEmailStatusBadge } from '@/features/work-order-emails/work-order-email-status-badge'
import type { OutboundEmailListItem } from '@/features/work-order-emails/types'

const SKELETON_ROWS = 3
const RECIPIENTS_PREVIEW_COUNT = 2

interface WorkOrderEmailListProps {
  workOrderId: number
  onSelect: (item: OutboundEmailListItem) => void
}

/** Truncated "A" preview: the first couple of addresses, "+N" for the rest. */
function recipientsPreview(to: string[]): string {
  if (to.length === 0) {
    return '—'
  }
  const shown = to.slice(0, RECIPIENTS_PREVIEW_COUNT).join(', ')
  const extra = to.length - RECIPIENTS_PREVIEW_COUNT
  return extra > 0 ? `${shown} +${extra}` : shown
}

/**
 * Paginated history (AC-019): status badge, subject, truncated "A", sender,
 * date and attachment count. A row click is delegated entirely to the
 * caller — this component knows nothing about the composer/detail dialogs.
 */
export function WorkOrderEmailList({ workOrderId, onSelect }: WorkOrderEmailListProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch, hasNextPage, isFetchingNextPage, fetchNextPage } =
    useWorkOrderEmailsList(workOrderId)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
          <Skeleton key={index} className="h-16 w-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2">
        <p role="alert" className="flex items-center gap-1.5 text-xs text-destructive">
          <AlertCircle className="size-3.5 shrink-0" aria-hidden="true" />
          {t('workOrderEmails.tab.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const items = data?.pages.flatMap((page) => page.data) ?? []

  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center gap-1.5 rounded-xl border border-dashed border-muted-foreground/25 bg-muted/20 px-4 py-8 text-center">
        <span className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <MailOpen className="size-5" aria-hidden="true" />
        </span>
        <p className="text-sm font-medium text-foreground">{t('workOrderEmails.tab.empty')}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <ul className="flex flex-col gap-2">
        {items.map((item) => (
          <li key={item.id}>
            <button
              type="button"
              onClick={() => onSelect(item)}
              className="flex w-full flex-col gap-1 rounded-lg border bg-card p-2.5 text-left text-xs shadow-sm transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50"
            >
              <div className="flex items-center justify-between gap-2">
                <WorkOrderEmailStatusBadge status={item.status} />
                <span className="text-muted-foreground">
                  {formatDateTime(item.sent_at ?? item.failed_at ?? item.updated_at)}
                </span>
              </div>
              <span className="truncate font-medium text-foreground">
                {item.subject || t('workOrderEmails.tab.columns.subject')}
              </span>
              <div className="flex items-center justify-between gap-2 text-muted-foreground">
                <span className="min-w-0 truncate">{recipientsPreview(item.to)}</span>
                <span className="flex shrink-0 items-center gap-2">
                  <span className="max-w-24 truncate">{item.sender.name}</span>
                  {item.attachments_count > 0 ? (
                    <span className="flex items-center gap-0.5">
                      <Paperclip className="size-3" aria-hidden="true" />
                      {item.attachments_count}
                    </span>
                  ) : null}
                </span>
              </div>
            </button>
          </li>
        ))}
      </ul>
      {hasNextPage ? (
        <Button variant="outline" size="sm" onClick={() => fetchNextPage()} disabled={isFetchingNextPage}>
          {isFetchingNextPage ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
          {t('workOrderEmails.tab.loadMore')}
        </Button>
      ) : null}
    </div>
  )
}
