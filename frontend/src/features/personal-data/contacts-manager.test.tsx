import { beforeAll, describe, expect, it, vi } from 'vitest'
import { useState, type ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import type { ContactDraft } from '@/features/personal-data/types'

/**
 * ContactsManager consumes `useConfirm` (dialog) and `useEnumOptions` (config
 * query), so every render needs both providers — the same the app mounts.
 */
function renderWithConfirm(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

// Stub the inline editor so the manager's buffer logic is tested in isolation,
// without pulling in the config (enum options) the real form needs. The stub
// submits a fixed primary email contact.
vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: ({
    onSubmit,
  }: {
    onSubmit: (fields: Omit<ContactDraft, '_key'>) => void
  }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() =>
        onSubmit({ type: 'email', value: 'new@example.com', label: null, is_primary: true })
      }
    >
      stub-save
    </button>
  ),
}))

// Immediate-persistence path hits the per-entity contact endpoints.
const createContactMock = vi.fn()
const updateContactMock = vi.fn()
const deleteContactMock = vi.fn()
vi.mock('@/features/personal-data/api', () => ({
  createContact: (...a: unknown[]) => createContactMock(...a),
  updateContact: (...a: unknown[]) => updateContactMock(...a),
  deleteContact: (...a: unknown[]) => deleteContactMock(...a),
}))

function contact(overrides: Partial<ContactDraft> = {}): ContactDraft {
  return {
    _key: 'contact-1',
    id: 1,
    type: 'email',
    label: 'Work',
    value: 'ada@example.com',
    is_primary: true,
    ...overrides,
  }
}

describe('ContactsManager (controlled)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('shows the empty state when there are no contacts', () => {
    renderWithConfirm(<ContactsManager value={[]} onChange={() => {}} />)
    expect(screen.getByText('No contacts yet.')).toBeInTheDocument()
  })

  it('renders each contact value, its label and the primary badge', () => {
    renderWithConfirm(<ContactsManager value={[contact()]} onChange={() => {}} />)
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
    expect(screen.getByText('Work')).toBeInTheDocument()
    expect(screen.getByText('Primary')).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Edit contact' }),
    ).toBeInTheDocument()
  })

  it('removes a contact from the buffer without any network call', async () => {
    const onChange = vi.fn()

    renderWithConfirm(<ContactsManager value={[contact()]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete contact' }))

    // Confirm through the reusable dialog (scoped so it isn't the row's button).
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete contact' }))

    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
    expect(onChange).toHaveBeenCalledTimes(1)
  })

  it('appends a new contact and demotes the previous primary of that type', () => {
    const onChange = vi.fn()
    const existing = contact({ _key: 'contact-1', id: 1, is_primary: true })

    renderWithConfirm(<ContactsManager value={[existing]} onChange={onChange} />)
    fireEvent.click(screen.getByRole('button', { name: 'Add contact' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as ContactDraft[]
    expect(next).toHaveLength(2)
    // The new email contact is primary; the prior email is demoted (per-type).
    expect(next[0].is_primary).toBe(false)
    expect(next[1].value).toBe('new@example.com')
    expect(next[1].is_primary).toBe(true)
    expect(next[1].id).toBeUndefined()
  })

  it('persists a new contact immediately and seeds the buffer with the server id', async () => {
    const onChange = vi.fn()
    createContactMock.mockResolvedValue({
      id: 42,
      type: 'email',
      label: null,
      value: 'new@example.com',
      is_primary: true,
      contactable_type: 'personal_data',
      contactable_id: 99,
      created_at: null,
    })

    renderWithConfirm(
      <ContactsManager
        value={[]}
        onChange={onChange}
        persistence={{ type: 'personal_data', id: 99 }}
      />,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Add contact' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    // The write targets the card owner, and the buffer is synced with the row id.
    await waitFor(() =>
      expect(createContactMock).toHaveBeenCalledWith(
        expect.objectContaining({
          contactable_type: 'personal_data',
          contactable_id: 99,
          value: 'new@example.com',
        }),
      ),
    )
    await waitFor(() => expect(onChange).toHaveBeenCalled())
    const next = onChange.mock.calls[0][0] as ContactDraft[]
    expect(next[0].id).toBe(42)
  })

  it('deletes a persisted contact immediately through the endpoint', async () => {
    const onChange = vi.fn()
    deleteContactMock.mockResolvedValue(undefined)

    renderWithConfirm(
      <ContactsManager
        value={[contact()]}
        onChange={onChange}
        persistence={{ type: 'personal_data', id: 99 }}
      />,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Delete contact' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete contact' }))

    await waitFor(() => expect(deleteContactMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(onChange).toHaveBeenCalledWith([]))
  })
})

/** Owns the buffer across renders, mirroring how a parent form would. */
function ControlledContacts({ initial = [] }: { initial?: ContactDraft[] }) {
  const [value, setValue] = useState<ContactDraft[]>(initial)
  return <ContactsManager value={value} onChange={setValue} createMode />
}

describe('ContactsManager (createMode)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('renders a quick field for email, phone, pec, fax and website', () => {
    renderWithConfirm(<ContactsManager value={[]} onChange={() => {}} createMode />)
    expect(screen.getByLabelText('Email')).toBeInTheDocument()
    expect(screen.getByLabelText('Phone')).toBeInTheDocument()
    expect(screen.getByLabelText('PEC')).toBeInTheDocument()
    expect(screen.getByLabelText('Fax')).toBeInTheDocument()
    expect(screen.getByLabelText('Website')).toBeInTheDocument()
  })

  it('creates a draft when typing into an empty quick field', () => {
    const onChange = vi.fn()
    renderWithConfirm(<ContactsManager value={[]} onChange={onChange} createMode />)

    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'new@example.com' },
    })

    expect(onChange).toHaveBeenCalledTimes(1)
    const next = onChange.mock.calls[0][0] as ContactDraft[]
    expect(next).toHaveLength(1)
    expect(next[0]).toMatchObject({
      type: 'email',
      value: 'new@example.com',
      label: null,
      is_primary: true,
    })
  })

  it('replaces the value of the existing quick-owned draft', () => {
    const onChange = vi.fn()
    const existing = contact({ _key: 'draft-1', id: undefined, type: 'email', value: 'a@example.com' })
    renderWithConfirm(<ContactsManager value={[existing]} onChange={onChange} createMode />)

    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'b@example.com' },
    })

    expect(onChange).toHaveBeenCalledWith([{ ...existing, value: 'b@example.com' }])
  })

  it('removes the draft once its quick field is emptied', () => {
    const onChange = vi.fn()
    const existing = contact({ _key: 'draft-1', id: undefined, type: 'phone', value: '+39 02 1234567' })
    renderWithConfirm(<ContactsManager value={[existing]} onChange={onChange} createMode />)

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '' } })

    expect(onChange).toHaveBeenCalledWith([])
  })

  it('shows an accessible error for an invalid quick email', () => {
    renderWithConfirm(<ControlledContacts />)
    const email = screen.getByLabelText('Email')

    fireEvent.change(email, { target: { value: 'not-an-email' } })

    expect(email).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('alert')).toHaveTextContent('Enter a valid email address.')
  })

  it('shows an accessible error for an invalid quick website', () => {
    renderWithConfirm(<ControlledContacts />)
    const website = screen.getByLabelText('Website')

    fireEvent.change(website, { target: { value: 'not a url' } })

    expect(website).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('alert')).toHaveTextContent('Enter a valid URL.')
  })

  it('still allows adding an extra contact via the dialog, excluded from the quick fields', () => {
    renderWithConfirm(<ControlledContacts />)

    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'first@example.com' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Add contact' }))
    fireEvent.click(screen.getByTestId('stub-submit'))

    // The dialog-added contact shows in the list; the quick-owned one does not.
    expect(screen.getByText('new@example.com')).toBeInTheDocument()
    expect(screen.queryByText('first@example.com')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toHaveValue('first@example.com')
  })
})
