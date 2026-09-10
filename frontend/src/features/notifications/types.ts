/** Severity level carried by a notification payload, used for visual accents. */
export type NotificationLevel = 'info' | 'success' | 'warning' | 'error'

/**
 * Domain-agnostic payload of a notification. The backend normalizes every row
 * (NotificationData value object) so all four keys are ALWAYS present: `level`
 * is always a valid value, while `title`/`message`/`action_url` may be null when
 * the producing notification omitted them — the UI applies its own fallbacks.
 */
export interface NotificationData {
  title: string | null
  message: string | null
  level: NotificationLevel
  action_url: string | null
}

/** A single notification as returned by the backend. */
export interface Notification {
  id: string
  type: string
  data: NotificationData
  /** ISO timestamp when the notification was read, or null while unread. */
  read_at: string | null
  created_at: string
}

/** Pagination metadata returned inside the list envelope. */
export interface Pagination {
  total: number
  offset: number
  limit: number
  total_pages: number
}

/**
 * Paginated list envelope returned by the notifications list endpoint. This is
 * the raw response body (not wrapped in {@link ApiResponse}).
 */
export interface PaginatedResponse<T> {
  items: T[]
  export_link: string | null
  pagination: Pagination
}

/** Filter applied to the notifications list query. */
export type NotificationFilter = 'all' | 'unread' | 'read'

/**
 * Payload of the polled unread endpoint: how many notifications are unread and
 * the most recent unread one (`null` when `count` is 0), used to announce the
 * notification in the browser tab title.
 */
export interface UnreadSummary {
  count: number
  latest: Notification | null
}
