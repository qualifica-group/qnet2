import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen } from '@testing-library/react'
import { useState } from 'react'
import { FormTabStrip, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { Tabs, TabsContent, TabsTrigger } from '@/components/ui/tabs'

/**
 * The module tab strip collapses into a select as soon as the tabs stop fitting
 * their container. jsdom computes no layout, so the widths the strip measures
 * are stubbed and the (stubbed) ResizeObserver is fired by hand.
 */

const resizeCallbacks: ResizeObserverCallback[] = []
const originalResizeObserver = globalThis.ResizeObserver

class ControllableResizeObserver implements ResizeObserver {
  constructor(callback: ResizeObserverCallback) {
    resizeCallbacks.push(callback)
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

function TabbedFixture() {
  const [tab, setTab] = useState('offer')

  return (
    <Tabs value={tab} onValueChange={setTab}>
      <FormTabStrip value={tab} onValueChange={setTab}>
        <TabsTrigger value="offer" className={FORM_TAB_TRIGGER_CLASS}>
          Offerta
        </TabsTrigger>
        <TabsTrigger value="costs" className={FORM_TAB_TRIGGER_CLASS}>
          Costi
        </TabsTrigger>
      </FormTabStrip>
      <TabsContent value="offer">offer panel</TabsContent>
      <TabsContent value="costs">costs panel</TabsContent>
    </Tabs>
  )
}

/** Stubs the strip's measurements, then fires every observer the strip registered. */
function measure({ listWidth, containerWidth }: { listWidth: number; containerWidth: number }) {
  const list = screen.getByRole('tablist')
  Object.defineProperty(list, 'scrollWidth', { value: listWidth, configurable: true })
  Object.defineProperty(list.parentElement, 'clientWidth', {
    value: containerWidth,
    configurable: true,
  })

  act(() => {
    for (const callback of resizeCallbacks) {
      callback([], {} as ResizeObserver)
    }
  })
}

beforeEach(() => {
  resizeCallbacks.length = 0
  globalThis.ResizeObserver = ControllableResizeObserver
})

afterEach(() => {
  globalThis.ResizeObserver = originalResizeObserver
  vi.restoreAllMocks()
})

describe('FormTabStrip', () => {
  it('keeps the tab strip while the tabs fit', () => {
    render(<TabbedFixture />)

    measure({ listWidth: 200, containerWidth: 400 })

    expect(screen.getByRole('tab', { name: 'Offerta' })).toBeVisible()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })

  it('collapses into a select once the tabs no longer fit', () => {
    render(<TabbedFixture />)

    measure({ listWidth: 600, containerWidth: 300 })

    const select = screen.getByRole('combobox')
    expect(select).toHaveTextContent('Offerta')
    // The strip stays mounted to keep being measurable, but out of sight and
    // out of the accessibility tree.
    expect(screen.getByRole('tablist', { hidden: true })).toHaveClass('invisible')
  })

  it('collapses when the tabs run past the screen even if their container does not', () => {
    render(<TabbedFixture />)

    // A container wider than the viewport: an ancestor sized by its content, or
    // an oversized sibling. The tabs fit the container and still leave the screen.
    measure({ listWidth: 1600, containerWidth: 2000 })

    expect(screen.getByRole('combobox')).toBeInTheDocument()
  })

  it('switches tab from the select and restores the strip when the space is back', () => {
    render(<TabbedFixture />)
    measure({ listWidth: 600, containerWidth: 300 })

    fireEvent.keyDown(screen.getByRole('combobox'), { key: 'Enter' })
    fireEvent.click(screen.getByRole('option', { name: 'Costi' }))

    expect(screen.getByText('costs panel')).toBeInTheDocument()

    measure({ listWidth: 600, containerWidth: 900 })

    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Costi' })).toHaveAttribute('data-state', 'active')
  })
})
