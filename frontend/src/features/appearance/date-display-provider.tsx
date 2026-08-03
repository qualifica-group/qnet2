import { Fragment, type ReactNode } from 'react'
import { useAuth } from '@/features/auth/use-auth'
import {
  applyDateDisplayPreferences,
  toDateFormat,
  toTimeFormat,
} from '@/lib/formatting/date-display'

/**
 * Keeps the app-wide date/time formatters in step with the authenticated user's
 * preferences (Settings → System). The authoritative value is the user's
 * `date_format`/`time_format`; a save primes the ['auth','me'] cache and flows
 * back here.
 *
 * Two deliberate choices:
 *  - the preference is written during render, not in an effect: the formatters
 *    are plain functions read by AG Grid renderers while the children below
 *    paint, and an effect would land one frame late (first paint on the old
 *    pattern). The write is idempotent and derived only from the user.
 *  - the subtree is keyed on the active pair, so changing the preference
 *    remounts every date on screen. The formatters are not hooks, so there is
 *    nothing for React to subscribe to; a remount is the honest way to refresh
 *    them, and the preference changes only on a deliberate user action.
 */
export function DateDisplayProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const dateFormat = toDateFormat(user?.date_format)
  const timeFormat = toTimeFormat(user?.time_format)

  applyDateDisplayPreferences(dateFormat, timeFormat)

  return <Fragment key={`${dateFormat}-${timeFormat}`}>{children}</Fragment>
}
