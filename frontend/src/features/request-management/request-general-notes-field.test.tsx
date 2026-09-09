import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { workPanel } from '@/features/request-management/request-work-panel-fixtures'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * "Note generali" in the work panel (direttiva utente 2026-09-09: "se non c'e'
 * una nota generale voglio che ci sia il componente e che possa essere
 * inserita o modificata, come anche in creazione"). Replaces the read-only
 * callout suite: what used to render NOTHING on a request with no note is now
 * the field the note is typed into.
 *
 * Mounted through the real panel, like the other section suites: the sparse
 * diff lives in `useRequestWorkForm` + `buildRequestWorkPayload`, not in the
 * component.
 */

const FIELD = 'General notes'

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Mirrors the backend derivation for a visible-but-not-editable field. */
const READ_ONLY_NOTES = {
  resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
  fields: {
    general_notes: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
  },
  actions: {},
}

function renderPanel(panel: RequestWorkPanelWithPermissions) {
  fetchRequestWorkPanelMock.mockResolvedValue(panel)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={panel.id} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

function save() {
  fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
})

describe('RequestGeneralNotesField', () => {
  it('renders an empty, editable field when the request carries no note', async () => {
    renderPanel(workPanel({ general_notes: null }))

    const field = await screen.findByRole('textbox', { name: FIELD })
    expect(field).toHaveValue('')
    expect(field).not.toHaveAttribute('readonly')
    expect(field).not.toBeDisabled()
  })

  it('shows the persisted note', async () => {
    renderPanel(workPanel({ general_notes: 'Recall the client in September' }))

    expect(await screen.findByRole('textbox', { name: FIELD })).toHaveValue(
      'Recall the client in September',
    )
  })

  it('sends ONLY general_notes when a note is typed on a request that had none', async () => {
    renderPanel(workPanel({ general_notes: null }))
    updateRequestWorkMock.mockResolvedValue(workPanel({ general_notes: 'Prefers the afternoon' }))

    fireEvent.change(await screen.findByRole('textbox', { name: FIELD }), {
      target: { value: 'Prefers the afternoon' },
    })
    save()

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({ general_notes: 'Prefers the afternoon' })
  })

  it('sends null once an existing note is emptied', async () => {
    renderPanel(workPanel({ general_notes: 'Da cancellare' }))
    updateRequestWorkMock.mockResolvedValue(workPanel({ general_notes: null }))

    fireEvent.change(await screen.findByRole('textbox', { name: FIELD }), { target: { value: '  ' } })
    save()

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({ general_notes: null })
  })

  it('omits the key when the note is untouched', async () => {
    renderPanel(workPanel({ general_notes: 'Invariata', next_callback_at: null }))
    updateRequestWorkMock.mockResolvedValue(workPanel({ general_notes: 'Invariata' }))

    await screen.findByRole('textbox', { name: FIELD })
    fireEvent.change(screen.getByLabelText('Callback date'), { target: { value: '2026-08-03' } })
    save()

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).not.toHaveProperty('general_notes')
  })

  it('stays read-only for an actor whose matrix locks the field', async () => {
    renderPanel(workPanel({ general_notes: 'Sola lettura', permissions: READ_ONLY_NOTES }))

    expect(await screen.findByRole('textbox', { name: FIELD })).toHaveAttribute('readonly')
  })
})
