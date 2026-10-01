import { useEffect } from 'react'
import { sendPresenceHeartbeat } from '@/features/system-health/api'

/** Cadence of the presence ping; the server counts "online" within a 2 minute window. */
export const PRESENCE_HEARTBEAT_INTERVAL_MS = 60000

/**
 * Keeps the signed-in user counted as online while the app is open (spec 0187).
 * Mounted once in `AppLayout`. Deliberately NOT a TanStack Query/mutation: the
 * ping carries no server state, and a mutation would re-render the layout on
 * every status change. A plain interval inside an effect is the legitimate
 * use here (subscription to a timer, cleaned up on unmount). Failures are
 * swallowed: presence is best-effort and must never surface an error.
 */
export function usePresenceHeartbeat(): void {
  useEffect(() => {
    const ping = () => {
      sendPresenceHeartbeat().catch(() => undefined)
    }

    ping()
    const timer = setInterval(ping, PRESENCE_HEARTBEAT_INTERVAL_MS)

    return () => clearInterval(timer)
  }, [])
}
