import { NotificationsTable } from '@/features/notifications/notifications-table'

/**
 * Notifications page (spec 0150). Unlike every other table page, no `<Can>`
 * gate: the domain has no Spatie permission (D-1) — every authenticated user
 * browses their OWN notifications, self-scoped server-side by the table's
 * `baseQuery()`.
 */
export default function NotificationsPage() {
  return (
    <div className="flex flex-1 flex-col gap-6">
      <NotificationsTable />
    </div>
  )
}
