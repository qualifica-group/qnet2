// @vitest-environment jsdom
import { renderHook } from '@testing-library/react'
import type { PointerEvent } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { useChartViewport } from '@/features/org-chart/use-chart-viewport'

function setup() {
  const viewport = document.createElement('div')
  const background = document.createElement('div')
  viewport.appendChild(background)
  const portal = document.createElement('div')
  portal.setAttribute('role', 'menuitem')
  document.body.append(viewport, portal)
  const setPointerCapture = vi.fn()
  viewport.setPointerCapture = setPointerCapture
  const { result } = renderHook(() => useChartViewport(1000, 800))
  result.current.viewportRef.current = viewport
  const down = (target: Element) =>
    result.current.panHandlers.onPointerDown({ button: 0, target, clientX: 100, clientY: 100, pointerId: 1 } as unknown as PointerEvent<HTMLDivElement>)
  const move = () => result.current.panHandlers.onPointerMove({ clientX: 60, clientY: 70 } as PointerEvent<HTMLDivElement>)
  return { viewport, background, portal, setPointerCapture, down, move }
}

describe('useChartViewport pan', () => {
  it('ignores presses whose target is outside the viewport DOM (portals: menus, hover cards)', () => {
    const { viewport, portal, setPointerCapture, down, move } = setup()
    down(portal)
    move()
    expect(setPointerCapture).not.toHaveBeenCalled()
    expect(viewport.scrollLeft).toBe(0)
  })

  it('pans when the press is on the background', () => {
    const { background, setPointerCapture, down } = setup()
    down(background)
    expect(setPointerCapture).toHaveBeenCalledWith(1)
  })
})
