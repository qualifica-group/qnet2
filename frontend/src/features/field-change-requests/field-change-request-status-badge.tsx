import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { enumLabelOf } from '@/features/config/enum-label'
import type { FieldChangeRequestStatus } from '@/features/field-change-requests/types'

/**
 * Status → badge color, frozen by the spec (0078) and mirrored from the
 * backend's `FieldChangeRequestColumnCatalog::STATUS_COLORS`: pending=amber,
 * approved=green, rejected=red. `FieldChangeRequestResource` carries no
 * color of its own (only the raw `status` string, unlike a grid `badge`
 * column's `badges` metadata) — every detail-context consumer of this
 * component (the request detail hero, the record's change-requests section)
 * recomputes it locally instead of duplicating the grid's metadata plumbing.
 */
const STATUS_COLORS: Record<FieldChangeRequestStatus, string> = {
  pending: 'amber',
  approved: 'green',
  rejected: 'red',
}

interface FieldChangeRequestStatusBadgeProps {
  status: FieldChangeRequestStatus
}

/** Plain colored status pill for a request shown outside the grid. */
export function FieldChangeRequestStatusBadge({ status }: FieldChangeRequestStatusBadgeProps) {
  const dotClass = swatchClassFor(STATUS_COLORS[status])
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(STATUS_COLORS[status]))}>
      {dotClass ? (
        <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" />
      ) : null}
      {enumLabelOf('field_change_request_status', status)}
    </Badge>
  )
}
