import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { WorkflowStatusNotify } from '@/features/workflow-statuses/types'
import { WorkflowStatusNotifyControl } from '@/features/workflow-statuses/workflow-status-notify-control'

/** Spec 0419 AC-007: the "send an email" switch of a working status and its configuration dialog. */

// Exercises the dialog's own wiring, not the async pickers' network path.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (value: number[]) => void }) => (
    <button type="button" onClick={() => onChange([31])}>
      {labels.triggerLabel}
    </button>
  ),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const CONFIGURED: WorkflowStatusNotify = {
  subject: 'Waiting for a quote',
  message: 'Prepare the quote.',
  record_team: false,
  user_ids: [7],
  role_ids: [],
  emails: ['backoffice@example.com'],
}

function renderControl(value: WorkflowStatusNotify | null) {
  const onChange = vi.fn()
  render(<WorkflowStatusNotifyControl rowId="custom-1" statusName="Waiting" value={value} disabled={false} onChange={onChange} />)

  return onChange
}

describe('WorkflowStatusNotifyControl', () => {
  it('switching on opens the dialog and saves subject, message and the offer team', async () => {
    const onChange = renderControl(null)

    fireEvent.click(screen.getByRole('switch', { name: 'Send an email notification' }))
    const dialog = await screen.findByRole('dialog', { name: 'Status email notification' })
    expect(dialog).toHaveTextContent('"Waiting"')

    fireEvent.change(screen.getByRole('textbox', { name: 'Subject' }), { target: { value: 'Waiting for a quote' } })
    fireEvent.change(screen.getByRole('textbox', { name: 'Message' }), { target: { value: 'Prepare the quote.' } })
    fireEvent.click(screen.getByRole('button', { name: 'Users' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save notification' }))

    await waitFor(() =>
      expect(onChange).toHaveBeenCalledWith({
        subject: 'Waiting for a quote',
        message: 'Prepare the quote.',
        record_team: true,
        user_ids: [31],
        role_ids: [],
        emails: [],
      }),
    )
  })

  it('refuses to save without subject, message or recipients', async () => {
    const onChange = renderControl(null)

    fireEvent.click(screen.getByRole('switch', { name: 'Send an email notification' }))
    await screen.findByRole('dialog')
    fireEvent.click(screen.getByRole('checkbox', { name: 'People on the offer' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save notification' }))

    expect(await screen.findByText('Choose at least one recipient')).toBeInTheDocument()
    expect(screen.getByText('Enter the subject')).toBeInTheDocument()
    expect(screen.getByText('Enter the message')).toBeInTheDocument()
    expect(onChange).not.toHaveBeenCalled()
  })

  it('cancelling a first configuration leaves the email off', async () => {
    const onChange = renderControl(null)

    fireEvent.click(screen.getByRole('switch', { name: 'Send an email notification' }))
    await screen.findByRole('dialog')
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(onChange).not.toHaveBeenCalled()
    expect(screen.getByRole('switch', { name: 'Send an email notification' })).not.toBeChecked()
  })

  it('shows a configured email and switching off removes it', () => {
    const onChange = renderControl(CONFIGURED)

    expect(screen.getByText('"Waiting for a quote" to 2 recipients')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('switch', { name: 'Send an email notification' }))

    expect(onChange).toHaveBeenCalledWith(null)
  })

  it('edit reopens the dialog with the saved values', async () => {
    renderControl(CONFIGURED)

    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))
    await screen.findByRole('dialog')

    expect(screen.getByRole('textbox', { name: 'Subject' })).toHaveValue('Waiting for a quote')
    expect(screen.getByText('backoffice@example.com')).toBeInTheDocument()
  })
})
