import { useEffect, useState } from 'react'

const TICK_MS = 30_000

/** Current time in ms, refreshed every 30s so the edit window closes without a rerender trigger. */
export function useNow(): number {
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), TICK_MS)
    return () => clearInterval(id)
  }, [])

  return now
}
