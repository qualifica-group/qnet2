import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronRight, Hourglass, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Progress } from '@/components/ui/progress'
import { DocumentRequestActions } from '@/features/document-requests/document-request-actions'
import { DocumentRequestItems } from '@/features/document-requests/document-request-items'
import { isOpen } from '@/features/document-requests/document-request-status'
import type { DocumentRequest, DocumentRequestStatus } from '@/features/document-requests/types'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

const STATUS_VARIANTS: Record<DocumentRequestStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  draft: 'outline',
  sent: 'secondary',
  in_progress: 'secondary',
  completed: 'default',
  expired: 'destructive',
  cancelled: 'outline',
}

function DeliveryNotice({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()

  if (request.delivery_status === 'pending') {
    return (
      <p role="status" className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Hourglass className="size-3.5" aria-hidden="true" />
        {t('documentRequests.delivery.pending')}
      </p>
    )
  }
  if (request.delivery_status === 'failed') {
    return (
      <p
        role="alert"
        className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive"
      >
        <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        <span>
          <span className="font-medium">{t('documentRequests.delivery.failed')}</span>
          {request.delivery_error ? `: ${request.delivery_error}` : null}
        </span>
      </p>
    )
  }

  return null
}

function RequestMeta({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()
  const entries: [string, string][] = [
    ['dueDate', request.due_date ? formatDate(request.due_date) : ''],
    ['recipient', request.recipient_email ?? ''],
    ['sentAt', request.sent_at ? formatDateTime(request.sent_at) : ''],
    ['lastRemindedAt', request.last_reminded_at ? formatDateTime(request.last_reminded_at) : ''],
  ]

  return (
    <dl className="flex flex-wrap gap-x-4 gap-y-0.5 text-xs">
      {entries
        .filter(([, value]) => value !== '')
        .map(([key, value]) => (
          <div key={key} className="flex gap-1">
            <dt className="text-muted-foreground">{t(`documentRequests.meta.${key}`)}:</dt>
            <dd className="break-all">{value}</dd>
          </div>
        ))}
    </dl>
  )
}

function RequestProgress({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()

  return (
    <div className="flex items-center gap-2">
      <Progress
        value={request.items_satisfied_count}
        max={Math.max(request.items_count, 1)}
        aria-label={t('documentRequests.progress.label')}
        className="flex-1"
      />
      <span className="whitespace-nowrap text-xs text-muted-foreground">
        {t('documentRequests.progress.count', { satisfied: request.items_satisfied_count, total: request.items_count })}
      </span>
    </div>
  )
}

function RequestDetails({ request }: { request: DocumentRequest }) {
  return (
    <>
      <RequestMeta request={request} />
      <DocumentRequestActions request={request} />
      <DocumentRequestItems request={request} />
    </>
  )
}

/**
 * One request: remote state, send state, progress bar, meta, actions and items by group (spec 0428 AC-017).
 * A closed request starts collapsed to its header and progress, and the title toggles it; an open one is always expanded.
 */
export function DocumentRequestCard({ request }: { request: DocumentRequest }) {
  const { t } = useTranslation()
  const collapsible = !isOpen(request)
  const [expanded, setExpanded] = useState(false)
  const title = request.title ?? request.checklist.name
  const statusLabel = request.status ? t(`documentRequests.status.${request.status}`) : t('documentRequests.status.unknown')

  return (
    <Collapsible open={!collapsible || expanded} onOpenChange={setExpanded} asChild>
      <article aria-label={title} className="flex flex-col gap-2 rounded-lg border bg-card p-3">
        <header className="flex flex-wrap items-center gap-2">
          <h3 className="min-w-0 flex-1 break-words text-base font-semibold">
            {collapsible ? (
              <CollapsibleTrigger className="flex w-full items-center gap-1.5 rounded-sm text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <ChevronRight
                  className={cn('size-4 shrink-0 transition-transform', expanded && 'rotate-90')}
                  aria-hidden="true"
                />
                {title}
              </CollapsibleTrigger>
            ) : (
              title
            )}
          </h3>
          {request.category_name ? <Badge variant="outline">{request.category_name}</Badge> : null}
          <Badge variant={request.status ? STATUS_VARIANTS[request.status] : 'outline'}>{statusLabel}</Badge>
        </header>
        <DeliveryNotice request={request} />
        <RequestProgress request={request} />
        <CollapsibleContent className="flex flex-col gap-2">
          <RequestDetails request={request} />
        </CollapsibleContent>
      </article>
    </Collapsible>
  )
}
