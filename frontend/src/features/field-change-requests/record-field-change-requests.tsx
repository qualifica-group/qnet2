import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDateTime } from '@/features/table/cell-renderers'
import { fetchFieldChangeRequestsForRecord } from '@/features/field-change-requests/api'
import { fieldChangeRequestKeys } from '@/features/field-change-requests/query-keys'
import { FieldChangeRequestActions } from '@/features/field-change-requests/field-change-request-actions'
import { FieldChangeRequestStatusBadge } from '@/features/field-change-requests/field-change-request-status-badge'
import type { FieldChangeRequestResource } from '@/features/field-change-requests/types'

const SKELETON_ROWS = 2

/** Only a proposal still awaiting a decision belongs on the record: a handled one lives on in the dedicated browse/detail. */
const PENDING_STATUS = 'pending'

/** Card layout for the inline Approve/Reject row: the card already owns the padding and the border. */
const CARD_ACTIONS_CLASS = 'flex flex-wrap items-center gap-2 pt-0.5'

export interface RecordFieldChangeRequestsProps {
  /** Resource key the record belongs to, e.g. "request-management" (spec 0078's `(resource, field)` indirection). */
  resource: string
  /** Id of the record whose change requests are listed. */
  subjectId: number
  /**
   * Called with the freshly resolved request once it is approved/rejected
   * from here. The host owns whatever else the decision invalidates — an
   * approved value is written on the record, so the screen showing it is
   * stale until the host refreshes it. Kept a callback because this section
   * knows nothing about the module it is mounted in (AC-054).
   */
  onHandled?: (request: FieldChangeRequestResource) => void
}

/**
 * Reusable "change requests" section for a record's detail (spec 0078,
 * AC-048): lists the field-change-requests still `pending` on the record —
 * with field, current → requested value, motivation, requester and date —
 * and, for an actor allowed to decide, the Approve/Reject buttons right on
 * the card (user directive 2026-08-04), so a proposal is resolved without
 * leaving the record. Handled proposals are deliberately NOT listed here:
 * they belong to the dedicated browse/detail. Order is the server's, never
 * re-sorted client-side. Deliberately generic (the spec's primary
 * architectural constraint, AC-054): it knows only the `(resource,
 * subjectId)` pair a caller hands it, never a specific field or module — no
 * import of Fonte/Gestione Richieste exists anywhere in this file.
 */
export function RecordFieldChangeRequests({
  resource,
  subjectId,
  onHandled,
}: RecordFieldChangeRequestsProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: fieldChangeRequestKeys.forRecord(resource, subjectId),
    queryFn: () => fetchFieldChangeRequestsForRecord(resource, subjectId),
  })

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
          <Skeleton key={index} className="h-14 w-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    // A 403 (no viewAny and not the requester of anything on this record) is
    // an authorization outcome, not a failure to surface: the section just
    // disappears instead of breaking the host page.
    if (axios.isAxiosError(error) && error.response?.status === 403) {
      return null
    }
    return (
      <div className="flex flex-col items-start gap-2">
        <p className="text-xs text-destructive">{t('fieldChangeRequests.section.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const pending = (data ?? []).filter((request) => request.status === PENDING_STATUS)

  if (pending.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('fieldChangeRequests.section.empty')}</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {pending.map((request) => (
        <FieldChangeRequestListItem key={request.id} request={request} onHandled={onHandled} />
      ))}
    </ul>
  )
}

interface FieldChangeRequestListItemProps {
  request: FieldChangeRequestResource
  onHandled?: (request: FieldChangeRequestResource) => void
}

function FieldChangeRequestListItem({ request, onHandled }: FieldChangeRequestListItemProps) {
  const { t } = useTranslation()
  const requestedAt = formatDateTime(request.requested_at)

  return (
    <li className="flex flex-col gap-1.5 rounded-lg border border-border bg-card px-3 py-2 text-sm">
      <div className="flex items-center justify-between gap-2">
        <span className="truncate font-medium">{t(request.field_label)}</span>
        <FieldChangeRequestStatusBadge status={request.status} />
      </div>
      <div className="flex items-center gap-1.5 truncate text-xs text-muted-foreground">
        <span className="truncate">
          {request.current_label ?? t('fieldChangeRequests.section.emptyValue')}
        </span>
        <span aria-hidden="true">→</span>
        <span className="truncate font-medium text-foreground">
          {request.requested_label ?? t('fieldChangeRequests.section.emptyValue')}
        </span>
      </div>
      {request.reason ? (
        <p className="truncate text-xs text-muted-foreground">{request.reason}</p>
      ) : null}
      <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <span className="truncate">{request.requested_by.name}</span>
        {requestedAt ? (
          <>
            <span aria-hidden="true">·</span>
            <span>{requestedAt}</span>
          </>
        ) : null}
      </div>
      {request.can.approve || request.can.reject ? (
        <FieldChangeRequestActions
          request={request}
          onHandled={(handled) => onHandled?.(handled)}
          className={CARD_ACTIONS_CLASS}
        />
      ) : null}
    </li>
  )
}
