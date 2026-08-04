import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RequestWorkHeader } from '@/features/request-management/request-work-header'
import { workPanel } from '@/features/request-management/request-work-panel-fixtures'

/**
 * The non-dismissible transfer notice (spec 0079 AC-023/AC-024/AC-025/AC-026):
 * derived purely from `panel.is_transferred` + `panel.transferred_from`, with
 * no close control and no dismiss state — asserted here by the absence of any
 * button inside it. The panel fixture's other required props are irrelevant
 * to this notice, so they stay at their neutral defaults.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const HEADER_PROPS = {
  canUpdate: true,
  formId: 'request-work-form',
  isSubmitting: false,
  isDirty: false,
  submitError: null,
  canTransfer: false,
  onTransfer: () => {},
}

describe('RequestWorkHeader — transfer notice (spec 0079)', () => {
  it('shows the notice with the origin Sede label when the request was transferred (AC-023)', () => {
    render(
      <RequestWorkHeader
        {...HEADER_PROPS}
        panel={workPanel({
          is_transferred: true,
          transferred_from: { id: 5, label: 'Via Roma 1 - Milano' },
        })}
      />,
    )

    expect(screen.getByText('Contact transferred from Via Roma 1 - Milano')).toBeInTheDocument()
  })

  it('renders no close/dismiss control on the notice (AC-024)', () => {
    render(
      <RequestWorkHeader
        {...HEADER_PROPS}
        panel={workPanel({
          is_transferred: true,
          transferred_from: { id: 5, label: 'Via Roma 1 - Milano' },
        })}
      />,
    )

    const notice = screen.getByText('Contact transferred from Via Roma 1 - Milano').closest('span')?.parentElement
    expect(notice?.querySelector('button')).toBeNull()
  })

  it('shows no notice for a request that was never transferred (AC-025)', () => {
    render(
      <RequestWorkHeader
        {...HEADER_PROPS}
        panel={workPanel({ is_transferred: false, transferred_from: null })}
      />,
    )

    expect(screen.queryByText(/Contact transferred from/)).not.toBeInTheDocument()
  })

  it('shows no notice when the origin Sede was deleted (is_transferred true, transferred_from null, AC-026)', () => {
    render(
      <RequestWorkHeader
        {...HEADER_PROPS}
        panel={workPanel({ is_transferred: true, transferred_from: null })}
      />,
    )

    expect(screen.queryByText(/Contact transferred from/)).not.toBeInTheDocument()
  })
})

/**
 * The "Trasferisci contatto" button next to Save (spec 0079 addendum): the
 * header only renders/wires it, the dialog and mutation live in the panel
 * (`request-work-panel-transfer.test.tsx` covers the click-to-submit flow).
 */
describe('RequestWorkHeader — transfer button (spec 0079 addendum)', () => {
  it('renders it when the actor can transfer, and calls onTransfer on click', () => {
    const onTransfer = vi.fn()
    render(
      <RequestWorkHeader {...HEADER_PROPS} panel={workPanel()} canTransfer onTransfer={onTransfer} />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Transfer contact' }))
    expect(onTransfer).toHaveBeenCalledTimes(1)
  })

  it('renders nothing when the actor cannot transfer', () => {
    render(<RequestWorkHeader {...HEADER_PROPS} panel={workPanel()} canTransfer={false} />)

    expect(screen.queryByRole('button', { name: 'Transfer contact' })).not.toBeInTheDocument()
  })
})
