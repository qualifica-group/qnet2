/* eslint-disable react-refresh/only-export-components -- renderer map module: cells are AG Grid render functions, not route/page components */
import type { MouseEvent, ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import type { ICellRendererParams } from 'ag-grid-community'
import { ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DateTimeCell, EmptyCell } from '@/features/table/cell-renderers'
import { findModuleRecordByPath } from '@/features/modules/module-registry'
import { useRecordModalLink } from '@/features/modules/use-record-modal-link'
import { useNotificationActions } from '@/features/notifications/use-notification-actions'
import { safeInternalPath } from '@/features/notifications/safe-internal-path'
import type { TableRendererMap } from '@/features/table/renderer-registry'

interface OpenLinkProps {
  to: string
  onClick: (event: MouseEvent<HTMLAnchorElement>) => void
  children?: ReactNode
}

/** The "Apri" affordance: a real link (new tab, copy), styled as a compact link button. */
function OpenLink({ to, onClick, children }: OpenLinkProps) {
  const { t } = useTranslation()
  return (
    <div className="flex h-full items-center">
      <Button asChild variant="link" size="xs" className="h-auto min-w-0 p-0">
        <Link to={to} onClick={onClick}>
          <ExternalLink aria-hidden="true" className="size-3.5" />
          {t('notifications.columns.actionUrlOpen')}
        </Link>
      </Button>
      {children}
    </div>
  )
}

interface RecordOpenLinkProps {
  path: string
  domain: string
  id: number
  onOpen: () => void
}

/**
 * Split out so the modal hook (which requires a registered domain) only mounts
 * once `findModuleRecordByPath` resolved one. The Sheet lives in this cell; the
 * row refresh after mark-read is non-purging, so the cell (and the open modal)
 * survive it.
 */
function RecordOpenLink({ path, domain, id, onOpen }: RecordOpenLinkProps) {
  const { onClick, sheet } = useRecordModalLink(domain, id)
  const handleClick = (event: MouseEvent<HTMLAnchorElement>) => {
    onOpen()
    onClick(event)
  }
  return (
    <OpenLink to={path} onClick={handleClick}>
      {sheet}
    </OpenLink>
  )
}

/**
 * `action_url` cell (spec 0150 D-3/AC-012): renders "Apri" only when the row
 * carries a safe internal path (`safeInternalPath`, same allow-list the
 * campanella's `NotificationItem` enforces); empty otherwise. A click on an
 * unread row marks it read (D-7 invalidates `notificationKeys.all` through
 * the shared mutation, then the grid reloads without purging). A record path
 * of a registered module opens that record in a MODAL over the page; any other
 * path (e.g. an import run) is followed as a plain link.
 */
function ActionUrlCell({ data, api }: ICellRendererParams) {
  const { markAsRead } = useNotificationActions()

  const rawUrl = typeof data?.action_url === 'string' ? data.action_url : null
  const targetPath = safeInternalPath(rawUrl)

  if (!data || !targetPath) {
    return <EmptyCell />
  }

  const markReadIfUnread = () => {
    if (data.status === 'unread') {
      markAsRead.mutate(String(data.id), {
        onSuccess: () => api?.refreshServerSide({ purge: false }),
      })
    }
  }

  const record = findModuleRecordByPath(targetPath)
  if (record) {
    return (
      <RecordOpenLink path={targetPath} domain={record.domain} id={record.id} onOpen={markReadIfUnread} />
    )
  }

  return <OpenLink to={targetPath} onClick={markReadIfUnread} />
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
