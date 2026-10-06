import { useEffect, useRef, useState } from 'react'

const COUNT_UP_DURATION_MS = 600
const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)'

/** False without `matchMedia` (tests, old engines) or when the user asked for reduced motion. */
function motionAllowed(): boolean {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    !window.matchMedia(REDUCED_MOTION_QUERY).matches
  )
}

/** Ease-out cubic: fast start, gentle landing on the final number. */
function easeOut(progress: number): number {
  return 1 - (1 - progress) ** 3
}

/**
 * The integer to DISPLAY while a number animates towards `target`: from 0 on
 * mount, then from whatever is on screen to each new target. Purely visual —
 * callers keep exposing the real `target` to assistive tech. Without motion
 * it returns `target` immediately.
 */
export function useCountUp(target: number): number {
  const [animate] = useState(motionAllowed)
  const [displayed, setDisplayed] = useState(animate ? 0 : target)
  const displayedRef = useRef(displayed)

  useEffect(() => {
    if (!animate) {
      return
    }

    const from = displayedRef.current
    const start = performance.now()
    let frame = 0
    const tick = (now: number) => {
      const progress = Math.min((now - start) / COUNT_UP_DURATION_MS, 1)
      const next = Math.round(from + (target - from) * easeOut(progress))
      displayedRef.current = next
      setDisplayed(next)
      if (progress < 1) {
        frame = requestAnimationFrame(tick)
      }
    }
    frame = requestAnimationFrame(tick)

    return () => cancelAnimationFrame(frame)
  }, [animate, target])

  return animate ? displayed : target
}
