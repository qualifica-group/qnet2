import { useEffect, useState } from 'react'

const TICK_MS = 1000

/** The current time in ms, refreshed every second while `active` (a countdown). */
export function useNow(active: boolean): number {
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (!active) {
      return
    }
    const id = setInterval(() => setNow(Date.now()), TICK_MS)
    return () => clearInterval(id)
  }, [active])

  return now
}
