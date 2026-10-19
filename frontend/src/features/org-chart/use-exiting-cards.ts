import { useEffect, useRef, useState } from 'react'
import type { ChartCard, ChartEdge } from '@/features/org-chart/chart-layout'

/** Duration of the leave animation; the removed cards stay mounted this long. */
export const EXIT_MS = 200

interface Exiting {
  cards: ChartCard[]
  edges: ChartEdge[]
}

const NONE: Exiting = { cards: [], edges: [] }

function prefersReducedMotion(): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

/**
 * Keeps the cards (and edges) that left the layout mounted for `EXIT_MS`, so
 * collapsing plays a leave animation instead of removing them at once. They
 * are purely visual: the caller renders them inert and outside the tree.
 */
export function useExitingCards(cards: ChartCard[], edges: ChartEdge[]): Exiting {
  const previous = useRef<Exiting>({ cards, edges })
  const [exiting, setExiting] = useState<Exiting>(NONE)

  useEffect(() => {
    const before = previous.current
    previous.current = { cards, edges }
    if (prefersReducedMotion()) return
    const liveCards = new Set(cards.map((card) => card.node.key))
    const liveEdges = new Set(edges.map((edge) => edge.key))
    const removedCards = before.cards.filter((card) => !liveCards.has(card.node.key))
    const removedEdges = before.edges.filter((edge) => !liveEdges.has(edge.key))
    if (removedCards.length === 0 && removedEdges.length === 0) return
    // Synchronizes with a timer: the removed items are held, then released.
    setExiting({ cards: removedCards, edges: removedEdges })
    const timer = setTimeout(() => setExiting(NONE), EXIT_MS)
    return () => clearTimeout(timer)
  }, [cards, edges])

  // A card that came back before the timer fired is live again, never duplicated.
  const liveKeys = new Set(cards.map((card) => card.node.key))
  const liveEdgeKeys = new Set(edges.map((edge) => edge.key))
  return {
    cards: exiting.cards.filter((card) => !liveKeys.has(card.node.key)),
    edges: exiting.edges.filter((edge) => !liveEdgeKeys.has(edge.key)),
  }
}
