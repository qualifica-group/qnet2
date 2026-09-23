import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCollaborationCard } from '@/components/detail/record-collaboration-card'

const TABS = [
  { value: 'notes', label: 'Notes', icon: null, content: <p>notes body</p> },
  { value: 'activity', label: 'Activity', icon: null, content: <p>activity body</p> },
]

describe('RecordCollaborationCard', () => {
  it('renders one tab per entry with the first one active', () => {
    render(<RecordCollaborationCard tabs={TABS} />)

    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Activity' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.getByText('notes body')).toBeInTheDocument()
  })

  it('switches content on tab activation', () => {
    render(<RecordCollaborationCard tabs={TABS} />)

    // Radix Tabs activate on mousedown, not click.
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Activity' }), { button: 0 })

    expect(screen.getByText('activity body')).toBeInTheDocument()
  })

  it('renders nothing when no tab is authorized', () => {
    const { container } = render(<RecordCollaborationCard tabs={[]} />)

    expect(container).toBeEmptyDOMElement()
  })
})

describe('RecordBody', () => {
  it('lays out the side column only when one is given', () => {
    const { container, rerender } = render(<RecordBody side={<p>side</p>}>main</RecordBody>)

    expect(container.firstElementChild?.children).toHaveLength(2)

    rerender(<RecordBody side={null}>main</RecordBody>)

    expect(container.firstElementChild?.children).toHaveLength(1)
    expect(screen.queryByText('side')).not.toBeInTheDocument()
  })
})
