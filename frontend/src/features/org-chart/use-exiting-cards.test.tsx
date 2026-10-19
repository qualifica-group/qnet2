// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { layoutChart } from '@/features/org-chart/chart-layout'
import { buildFunctionTree } from '@/features/org-chart/build-function-tree'
import { FUNCTIONS_FIXTURE } from '@/features/org-chart/fixtures.test-support'
import { EXIT_MS, useExitingCards } from '@/features/org-chart/use-exiting-cards'

const nodes = buildFunctionTree(FUNCTIONS_FIXTURE)
const OPEN = layoutChart(nodes, new Set())
const CLOSED = layoutChart(nodes, new Set(['f:1']))

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('useExitingCards', () => {
  it('holds the removed cards for EXIT_MS, then releases them', () => {
    const { result, rerender } = renderHook(({ layout }) => useExitingCards(layout.cards, layout.edges), {
      initialProps: { layout: OPEN },
    })
    expect(result.current.cards).toEqual([])
    rerender({ layout: CLOSED })
    const removed = OPEN.cards.filter((card) => !CLOSED.cards.some((live) => live.node.key === card.node.key))
    expect(removed.length).toBeGreaterThan(0)
    expect(result.current.cards.map((card) => card.node.key)).toEqual(removed.map((card) => card.node.key))
    act(() => vi.advanceTimersByTime(EXIT_MS))
    expect(result.current.cards).toEqual([])
  })

  it('never lists a card that is live again', () => {
    const { result, rerender } = renderHook(({ layout }) => useExitingCards(layout.cards, layout.edges), {
      initialProps: { layout: OPEN },
    })
    rerender({ layout: CLOSED })
    rerender({ layout: OPEN })
    expect(result.current.cards).toEqual([])
  })
})
