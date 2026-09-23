/* eslint-disable react-refresh/only-export-components -- renderer map module: cells are AG Grid render functions, not route/page components */
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import { ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import { useNotificationActions } from '@/features/notifications/use-notification-actions'
import { safeInternalPath } from '@/features/notifications/safe-internal-path'
import type { TableRendererMap } from '@/features/table/renderer-registry'

/**
 * `action_url` cell (spec 0150 D-3/AC-012): renders "Apri" only when the row
 * carries a safe internal path (`safeInternalPath`, same allow-list the
 * campanella's `NotificationItem` enforces); empty otherwise. A click on an
 * unread row marks it read (D-7 invalidates `notificationKeys.all` through
 * the shared mutation) before navigating; an already-read row only
 * navigates. The grid itself is not refreshed here — the click leaves the
 * page immediately, unlike the row/bulk/header actions that stay on it.
 */
function ActionUrlCell({ data }: ICellRendererParams) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { markAsRead } = useNotificationActions()

  const rawUrl = typeof data?.action_url === 'string' ? data.action_url : null
  const targetPath = safeInternalPath(rawUrl)

  if (!data || !targetPath) {
    return <EmptyCell />
  }

  const handleOpen = () => {
    if (data.status === 'unread') {
      markAsRead.mutate(String(data.id))
    }
    void navigate(targetPath)
  }

  return (
    <Button
      type="button"
      variant="link"
      size="xs"
      className="h-auto min-w-0 p-0"
      onClick={handleOpen}
    >
      <ExternalLink aria-hidden="true" className="size-3.5" />
      {t('notifications.columns.actionUrlOpen')}
    </Button>
  )
}

/**
 * Custom cell renderers for the `notifications` domain (spec 0150). `status`
 * and `level` need none: both are native `badge` columns with an `enumKey`
 * (`notification_status`/`notification_level`), so the generic table already
 * resolves them through the shared `BadgeCell` fallback
 * (`column-defaults.tsx`) — a textual label, never color alone (AC-009).
 */
export const notificationColumnRenderers: TableRendererMap = {
  created_at: (params) => <DateTimeCell {...params} />,
  read_at: (params) => <DateTimeCell {...params} />,
  action_url: (params) => <ActionUrlCell {...params} />,
}
