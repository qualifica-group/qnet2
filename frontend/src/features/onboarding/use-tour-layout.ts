import { useEffect, useState } from 'react'
import type { Box, Size } from '@/features/onboarding/card-position'
import { ONBOARDING_ANCHOR_ATTRIBUTE } from '@/features/onboarding/steps'

export interface TourLayout {
  /** Step ids whose anchor is rendered with a non-empty box. */
  presentIds: readonly string[]
  /** Box of the current step's anchor, null when it is not on screen. */
  target: Box | null
  viewport: Size
}

const EMPTY_LAYOUT: TourLayout = { presentIds: [], target: null, viewport: { width: 0, height: 0 } }

/**
 * Follows the anchors on screen while the tour is open. A per-frame read keeps
 * the spotlight glued to its target through the side menu animation, window
 * resizes and the UI scale, with no observer per element; state only changes
 * when the measured layout does, so a still screen does not re-render.
 */
export function useTourLayout(active: boolean, stepId: string | null): TourLayout {
  const [layout, setLayout] = useState<TourLayout>(EMPTY_LAYOUT)

  useEffect(() => {
    if (!active) {
      return
    }
    let frame = 0
    let signature = ''

    const tick = () => {
      const next = readLayout(stepId)
      const nextSignature = JSON.stringify(next)
      if (nextSignature !== signature) {
        signature = nextSignature
        setLayout(next)
      }
      frame = window.requestAnimationFrame(tick)
    }

    tick()
    return () => window.cancelAnimationFrame(frame)
  }, [active, stepId])

  return active ? layout : EMPTY_LAYOUT
}

function readLayout(stepId: string | null): TourLayout {
  const presentIds: string[] = []
  let target: Box | null = null

  document.querySelectorAll<HTMLElement>(`[${ONBOARDING_ANCHOR_ATTRIBUTE}]`).forEach((element) => {
    const rect = element.getBoundingClientRect()
    const id = element.getAttribute(ONBOARDING_ANCHOR_ATTRIBUTE)
    // An empty wrapper (its button rendered nothing) has no box: not on screen.
    if (!id || (rect.width === 0 && rect.height === 0)) {
      return
    }
    presentIds.push(id)
    if (id === stepId) {
      target = {
        top: Math.round(rect.top),
        left: Math.round(rect.left),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
      }
    }
  })

  return { presentIds, target, viewport: { width: window.innerWidth, height: window.innerHeight } }
}
