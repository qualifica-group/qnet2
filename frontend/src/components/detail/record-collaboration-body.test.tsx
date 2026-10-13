import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { TooltipProvider } from '@/components/ui/tooltip'
import i18n from '@/i18n'

const TABS = [
  { value: 'notes', label: 'Notes', icon: null, content: <p>notes body</p> },
  { value: 'activity', label: 'Activity', icon: null, content: <p>activity body</p> },
]

const NARROW_BODY_WIDTH = 600
const WIDE_BODY_WIDTH = 1400

function renderBody(tabs: RecordCollaborationTab[] = TABS, sections: ReactNode = <p>record sections</p>) {
  return render(
    <TooltipProvider>
      <RecordBody collaborationTabs={tabs} header={<p>identity band</p>}>
        {sections}
      </RecordBody>
    </TooltipProvider>,
  )
}

function mockBodyWidth(width: number) {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ width } as DOMRect)
}

// Radix Tabs activate on mousedown, not click.
function activateTab(name: string) {
  fireEvent.mouseDown(screen.getByRole('tab', { name }), { button: 0 })
}

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('RecordBody with collaboration tabs — wide', () => {
  it('starts collapsed to the rail when no preference is stored', () => {
    mockBodyWidth(WIDE_BODY_WIDTH)
    renderBody()

    expect(screen.getByRole('button', { name: i18n.t('common.recordSide.expand') })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Notes' })).not.toBeInTheDocument()
    expect(screen.queryByText('notes body')).not.toBeInTheDocument()
    expect(screen.getByText('record sections')).toBeInTheDocument()
  })

  it('lays the collaboration card out beside the record once opened, sections untouched', () => {
    window.localStorage.setItem('record.side.collapsed', 'false')
    mockBodyWidth(WIDE_BODY_WIDTH)
    renderBody()

    expect(screen.getByText('record sections')).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: i18n.t('common.recordSide.details') })).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByText('notes body')).toBeInTheDocument()
  })

  it('collapses to a rail, unmounts the faded-out card and remembers the choice', () => {
    window.localStorage.setItem('record.side.collapsed', 'false')
    mockBodyWidth(WIDE_BODY_WIDTH)
    renderBody()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.recordSide.collapse') }))

    const cardLayer = screen.getByText('notes body').closest('[inert]')
    expect(cardLayer).not.toBeNull()
    expect(window.localStorage.getItem('record.side.collapsed')).toBe('true')

    fireEvent.transitionEnd(cardLayer as Element)

    expect(screen.queryByText('notes body')).not.toBeInTheDocument()
    expect(screen.getByText('record sections')).toBeInTheDocument()
  })

  it('opens a rail tab in a floating panel without expanding, closed by a click outside', async () => {
    window.localStorage.setItem('record.side.collapsed', 'true')
    mockBodyWidth(WIDE_BODY_WIDTH)
    renderBody()

    expect(screen.queryByRole('tab', { name: 'Notes' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Activity' }))

    expect(await screen.findByText('activity body')).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Activity' })).not.toBeInTheDocument()
    expect(window.localStorage.getItem('record.side.collapsed')).toBe('true')

    fireEvent.pointerDown(screen.getByText('record sections'))

    await waitFor(() => expect(screen.queryByText('activity body')).not.toBeInTheDocument())
  })

  it('expands back to the card from the rail', () => {
    window.localStorage.setItem('record.side.collapsed', 'true')
    mockBodyWidth(WIDE_BODY_WIDTH)
    renderBody()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.recordSide.expand') }))

    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByText('notes body')).toBeInTheDocument()
    expect(window.localStorage.getItem('record.side.collapsed')).toBe('false')
  })
})

describe('RecordBody with collaboration tabs — narrow', () => {
  it('merges the collaboration tabs into the record strip, details first', () => {
    mockBodyWidth(NARROW_BODY_WIDTH)
    renderBody()

    const tabs = screen.getAllByRole('tab').map((tab) => tab.textContent)
    expect(tabs).toEqual([i18n.t('common.recordSide.details'), 'Notes', 'Activity'])
    expect(screen.getByText('identity band')).toBeInTheDocument()
    expect(screen.getByText('record sections')).toBeInTheDocument()
    expect(screen.queryByText('notes body')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('common.recordSide.collapse') })).not.toBeInTheDocument()
  })

  it('switches between the record and a collaboration tab, keeping the identity band', () => {
    mockBodyWidth(NARROW_BODY_WIDTH)
    renderBody()

    activateTab('Notes')

    expect(screen.getByText('notes body')).toBeInTheDocument()
    expect(screen.queryByText('record sections')).not.toBeInTheDocument()
    expect(screen.getByText('identity band')).toBeInTheDocument()

    activateTab(i18n.t('common.recordSide.details'))

    expect(screen.getByText('record sections')).toBeInTheDocument()
  })

  it('ignores a stored collapse: there is no side column to collapse', () => {
    window.localStorage.setItem('record.side.collapsed', 'true')
    mockBodyWidth(NARROW_BODY_WIDTH)
    renderBody()

    expect(screen.getByRole('tab', { name: 'Notes' })).toBeInTheDocument()
  })
})

describe('RecordBody without sections or tabs', () => {
  it('keeps a header-only record on its collaboration tabs when narrow, with no empty "Dettagli" tab', () => {
    mockBodyWidth(NARROW_BODY_WIDTH)
    renderBody(TABS, null)

    expect(screen.queryByRole('tab', { name: i18n.t('common.recordSide.details') })).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByText('notes body')).toBeInTheDocument()
    expect(screen.getByText('identity band')).toBeInTheDocument()
  })

  it('lays out the record card alone, with no side column, when there is no tab', () => {
    mockBodyWidth(WIDE_BODY_WIDTH)
    renderBody([])

    expect(screen.getByText('identity band')).toBeInTheDocument()
    expect(screen.getByText('record sections')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('common.recordSide.expand') })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
  })
})
