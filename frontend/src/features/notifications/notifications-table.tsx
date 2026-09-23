import { useCallback, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { CheckCheck, CheckCircle, Circle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { NOTIFICATIONS_DOMAIN } from '@/features/notifications/types'
import { notificationColumnRenderers } from '@/features/notifications/column-renderers'
import { useNotificationActions } from '@/features/notifications/use-notification-actions'

/**
 * `mark-read`/`mark-unread` icons (spec 0150, `NotificationColumnCatalog::actions()`):
 * not part of the shared default map, so the domain supplies them (same
 * convention as `LEADS_ACTION_ICONS`). Identity kept module-level: it flows
 * into `TableView`'s internal `useMemo` dependency list.
 */
const NOTIFICATIONS_ACTION_ICONS: ActionIconMap = {
  'check-circle': CheckCircle,
  circle: Circle,
}

/**
 * Thin notifications adapter over the generic table (spec 0150): every
 * authenticated user's OWN notifications, no `<Can>` gate (D-1, no Spatie
 * permission for this resource). Row actions flip the read state one at a
 * time; the bulk action and the header button do it for a selection or for
 * everything. Every mutation refreshes the grid and, through
 * `useNotificationActions`, invalidates `notificationKeys.all` (D-7) so the
 * bell badge and the tab title stay aligned.
 */
export function NotificationsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const { markAsRead, markAsUnread, markAllAsRead, markManyAsRead } = useNotificationActions()

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      const id = String(row.id)
      switch (action.key) {
        case 'mark-read':
          markAsRead.mutate(id, { onSuccess: refreshGrid })
          break
        case 'mark-unread':
          markAsUnread.mutate(id, { onSuccess: refreshGrid })
          break
        default:
          break
      }
    },
    [markAsRead, markAsUnread, refreshGrid],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => [
      {
        key: 'mark-selected-read',
        label: t('notifications.page.markSelectedAsRead'),
        icon: CheckCheck,
        disabled: markManyAsRead.isPending,
        onSelect: () => {
          const ids = selection.ids.map((id) => String(id))
          markManyAsRead.mutate(ids, {
            onSuccess: () => {
              tableRef.current?.clearSelection()
              refreshGrid()
            },
          })
        },
      },
    ],
    [markManyAsRead, refreshGrid, t],
  )

  const handleMarkAllAsRead = useCallback(() => {
    markAllAsRead.mutate(undefined, { onSuccess: refreshGrid })
  }, [markAllAsRead, refreshGrid])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={markAllAsRead.isPending}
            onClick={handleMarkAllAsRead}
          >
            <CheckCheck aria-hidden="true" />
            {t('notifications.page.markAllAsRead')}
          </Button>
        }
      />

      <TableView
        ref={tableRef}
        domain={NOTIFICATIONS_DOMAIN}
        renderers={notificationColumnRenderers}
        onAction={handleAction}
        iconMap={NOTIFICATIONS_ACTION_ICONS}
        getBulkActions={getBulkActions}
      />
    </div>
  )
}
