import { beforeEach, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { ClipboardList } from 'lucide-react'
import { AdditionalInformationSection } from '@/features/attributes/additional-information-section'

/**
 * The collapsible "Informazioni aggiuntive": open by default, the operator's
 * choice kept in the browser per module key. jsdom never plays the content's
 * CSS animation, so Radix `Presence` unmounts the body as soon as it closes.
 */

const QUOTES_KEY = 'quotes.additional-information'
const WORK_ORDERS_KEY = 'work-orders.additional-information'

function renderSection(storageKey: string) {
  return render(
    <AdditionalInformationSection storageKey={storageKey} title="Additional information" icon={<ClipboardList />}>
      <p>attribute rows</p>
    </AdditionalInformationSection>,
  )
}

describe('AdditionalInformationSection', () => {
  beforeEach(() => {
    window.localStorage.removeItem(QUOTES_KEY)
    window.localStorage.removeItem(WORK_ORDERS_KEY)
  })

  it('starts open when nothing is stored', () => {
    renderSection(QUOTES_KEY)

    expect(screen.getByRole('button', { name: 'Additional information' })).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByText('attribute rows')).toBeInTheDocument()
  })

  it('collapses on click and remembers it for the next record of the same module', () => {
    const { unmount } = renderSection(QUOTES_KEY)

    fireEvent.click(screen.getByRole('button', { name: 'Additional information' }))

    expect(screen.queryByText('attribute rows')).not.toBeInTheDocument()
    expect(window.localStorage.getItem(QUOTES_KEY)).toBe('closed')

    unmount()
    renderSection(QUOTES_KEY)

    expect(screen.getByRole('button', { name: 'Additional information' })).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByText('attribute rows')).not.toBeInTheDocument()
  })

  it('keeps each module on its own state', () => {
    window.localStorage.setItem(QUOTES_KEY, 'closed')

    renderSection(WORK_ORDERS_KEY)

    expect(screen.getByText('attribute rows')).toBeInTheDocument()
  })

  it('reopening clears the stored choice', () => {
    window.localStorage.setItem(QUOTES_KEY, 'closed')
    renderSection(QUOTES_KEY)

    fireEvent.click(screen.getByRole('button', { name: 'Additional information' }))

    expect(screen.getByText('attribute rows')).toBeInTheDocument()
    expect(window.localStorage.getItem(QUOTES_KEY)).toBeNull()
  })
})
