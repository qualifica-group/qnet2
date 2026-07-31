import { render, screen, act } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import {
  MIN_TABLE_HEIGHT,
  useViewportTableHeight,
} from '@/features/table/use-viewport-table-height'

const BOTTOM_GAP = 24
const originalGetBoundingClientRect = Element.prototype.getBoundingClientRect

/** jsdom reports every rect as zero; place the container at a known offset. */
function placeContainerAt(top: number) {
  Element.prototype.getBoundingClientRect = function () {
    return { ...new DOMRect(0, top, 0, 0), top } as DOMRect
  }
}

function setWindowMetric(key: 'innerHeight' | 'scrollY', value: number) {
  Object.defineProperty(window, key, { value, configurable: true })
}

function Probe({ enabled, maxHeight }: { enabled: boolean; maxHeight: number }) {
  const { containerRef, height } = useViewportTableHeight({ enabled, maxHeight })

  return (
    <div ref={containerRef}>
      <output>{height === null ? 'unset' : String(height)}</output>
    </div>
  )
}

function measuredHeight() {
  return screen.getByRole('status').textContent
}

afterEach(() => {
  Element.prototype.getBoundingClientRect = originalGetBoundingClientRect
  setWindowMetric('scrollY', 0)
})

describe('useViewportTableHeight', () => {
  it('fills the viewport left below the container offset', () => {
    placeContainerAt(180)
    setWindowMetric('innerHeight', 1000)

    render(<Probe enabled maxHeight={5000} />)

    expect(measuredHeight()).toBe(String(1000 - 180 - BOTTOM_GAP))
  })

  it('never exceeds the height the page of rows needs', () => {
    placeContainerAt(180)
    setWindowMetric('innerHeight', 2000)

    render(<Probe enabled maxHeight={760} />)

    expect(measuredHeight()).toBe('760')
  })

  it('falls back to half the viewport when the grid sits far down the page', () => {
    placeContainerAt(900)
    setWindowMetric('innerHeight', 1000)

    render(<Probe enabled maxHeight={5000} />)

    expect(measuredHeight()).toBe('500')
  })

  it('never drops below the absolute minimum on a very short viewport', () => {
    placeContainerAt(400)
    setWindowMetric('innerHeight', 500)

    render(<Probe enabled maxHeight={5000} />)

    expect(measuredHeight()).toBe(String(MIN_TABLE_HEIGHT))
  })

  it('keeps the content ceiling above the floor, so a short page draws no empty grid', () => {
    placeContainerAt(900)
    setWindowMetric('innerHeight', 1000)

    render(<Probe enabled maxHeight={200} />)

    expect(measuredHeight()).toBe('200')
  })

  it('measures from the top of the document, so scrolling does not grow the grid', () => {
    // A scrolled page reports a smaller viewport-relative top; adding scrollY
    // back cancels it out, which is what keeps the grid from growing on scroll.
    placeContainerAt(-120)
    setWindowMetric('scrollY', 300)
    setWindowMetric('innerHeight', 1000)

    render(<Probe enabled maxHeight={5000} />)

    expect(measuredHeight()).toBe(String(1000 - 180 - BOTTOM_GAP))
  })

  it('recomputes on window resize', () => {
    placeContainerAt(180)
    setWindowMetric('innerHeight', 1000)

    render(<Probe enabled maxHeight={5000} />)

    setWindowMetric('innerHeight', 700)
    act(() => {
      window.dispatchEvent(new Event('resize'))
    })

    expect(measuredHeight()).toBe(String(700 - 180 - BOTTOM_GAP))
  })

  it('reports no height when disabled, leaving the height to the flex parent', () => {
    placeContainerAt(180)
    setWindowMetric('innerHeight', 1000)

    render(<Probe enabled={false} maxHeight={5000} />)

    expect(measuredHeight()).toBe('unset')
  })
})
