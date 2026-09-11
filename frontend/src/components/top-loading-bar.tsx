import * as React from 'react'
import { useIsFetching, useIsMutating } from '@tanstack/react-query'
import { useNavigation } from 'react-router-dom'

/** Milliseconds between two synthetic progress ticks while work is in flight. */
const TICK_INTERVAL_MS = 200
/** Must match the width/opacity transition duration on the filled portion. */
const TRANSITION_MS = 200
/** How long activity must stay quiet before a run counts as finished. */
const IDLE_GRACE_MS = 120
/** Starting width, so even a very short request is perceivable. */
const INITIAL_PROGRESS = 16

/**
 * `running` advances the synthetic progress; `completing` travels to the right
 * edge while still opaque; `fading` dissolves the full-width bar before it
 * unmounts. Completing and fading have to be two separate transitions: merged
 * into one commit, a request that resolves in a few milliseconds fades away
 * mid-travel and the bar never visibly reaches the edge of the page.
 */
type BarPhase = 'idle' | 'running' | 'completing' | 'fading'

/**
 * There is no real completion percentage to show, so the bar eases towards 90%
 * and slows down as it advances: it never looks stuck, and the jump to 100% is
 * reserved for the moment the work actually ends.
 */
function nextProgress(current: number) {
  if (current < 40) {
    return current + 12
  }
  if (current < 70) {
    return current + 6
  }
  if (current < 90) {
    return current + 2
  }
  return current
}

/**
 * Global activity indicator pinned to the top edge of the viewport. It reacts to
 * router navigations plus any in-flight TanStack query or mutation; a mutation
 * opts out with `meta: { showGlobalLoading: false }`.
 */
export function TopLoadingBar() {
  const navigation = useNavigation()
  const isFetching = useIsFetching()
  const isMutating = useIsMutating({
    predicate: (mutation) => mutation.options.meta?.showGlobalLoading !== false,
  })
  const isActive = navigation.state !== 'idle' || isFetching > 0 || isMutating > 0

  const [phase, setPhase] = React.useState<BarPhase>('idle')
  const [progress, setProgress] = React.useState(0)
  const [wasActive, setWasActive] = React.useState(false)

  // Step 1: opening a run is a direct consequence of activity resuming, so it is
  // adjusted during render rather than in an effect — the bar has to be painted
  // at its starting width in the very commit that reveals it. Resuming while the
  // previous run is completing restarts the travel from the left.
  if (isActive !== wasActive) {
    setWasActive(isActive)
    if (isActive) {
      setPhase('running')
      setProgress(INITIAL_PROGRESS)
    }
  }

  // Step 2: advance the synthetic progress while work is in flight.
  React.useEffect(() => {
    if (!isActive) {
      return
    }

    const intervalId = window.setInterval(() => {
      setProgress((current) => nextProgress(current))
    }, TICK_INTERVAL_MS)

    return () => window.clearInterval(intervalId)
  }, [isActive])

  // Step 3: a run is finished only once activity has stayed quiet for a moment.
  // A waterfall of dependent queries leaves short gaps in `isActive`, and
  // completing on each of them would snap the bar back to the left mid-load.
  React.useEffect(() => {
    if (isActive || phase !== 'running') {
      return
    }

    const timeoutId = window.setTimeout(() => setPhase('completing'), IDLE_GRACE_MS)

    return () => window.clearTimeout(timeoutId)
  }, [isActive, phase])

  // Step 4: fade out only once the bar has actually travelled to the right edge,
  // then unmount once that fade has played.
  React.useEffect(() => {
    if (phase !== 'completing' && phase !== 'fading') {
      return
    }

    const timeoutId = window.setTimeout(() => {
      setPhase(phase === 'completing' ? 'fading' : 'idle')
    }, TRANSITION_MS)

    return () => window.clearTimeout(timeoutId)
  }, [phase])

  if (phase === 'idle') {
    return null
  }

  return (
    <div
      data-slot="top-loading-bar"
      aria-hidden="true"
      className="pointer-events-none fixed inset-x-0 top-0 z-[120] h-1"
    >
      <div
        className="h-full bg-loading-bar shadow-[0_0_12px_var(--loading-bar-glow)] transition-[width,opacity] duration-200 ease-out"
        style={{
          width: phase === 'running' ? `${progress}%` : '100%',
          opacity: phase === 'fading' ? 0 : 1,
        }}
      />
    </div>
  )
}
