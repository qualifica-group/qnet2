import { useCallback, useEffect, useState } from 'react'

/**
 * Guard for an unbounded poll: reports `isStalled` once `phase` has stayed
 * unchanged for longer than `timeoutMs`. Every polled flow in this app waits
 * on a queued job; a job nobody consumes (typically no `queue:work` worker
 * running) never reaches a terminal status, so without this the client keeps
 * hitting the same endpoint forever.
 *
 * `phase` must be `null` whenever nothing is being polled, and a NEW string
 * whenever the polled subject or its status changes (e.g. `${runId}:${status}`)
 * so each phase is given its own, full timeout window.
 */
export function useStallTimeout(phase: string | null, timeoutMs: number) {
  // The wait a stall was recorded for. Comparing it against the current wait
  // (rather than resetting a boolean) is what makes a phase change — or a
  // `resetStall` — clear the stall without writing state during render or
  // in an effect body.
  const [stalledWait, setStalledWait] = useState<string | null>(null)
  // Bumped by `resetStall` to open a new window on an unchanged `phase`.
  const [waitToken, setWaitToken] = useState(0)

  const currentWait = phase === null ? null : `${waitToken}:${phase}`

  useEffect(() => {
    if (currentWait === null) return

    const timeoutId = setTimeout(() => setStalledWait(currentWait), timeoutMs)
    return () => clearTimeout(timeoutId)
  }, [currentWait, timeoutMs])

  /** Restarts the timeout window, e.g. when the user asks to keep waiting. */
  const resetStall = useCallback(() => setWaitToken((token) => token + 1), [])

  return { isStalled: currentWait !== null && stalledWait === currentWait, resetStall }
}
