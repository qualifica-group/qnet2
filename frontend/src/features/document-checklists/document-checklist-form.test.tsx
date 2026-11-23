import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { DocumentChecklistForm } from '@/features/document-checklists/document-checklist-form'
import type {
  DocumentChecklistItem,
  DocumentChecklistSyncLog,
  DocumentChecklistWithPermissions,
} from '@/features/document-checklists/types'

const createMock = vi.fn()
const updateMock = vi.fn()
const syncLogsMock = vi.fn()

vi.mock('@/features/document-checklists/api', () => ({
  createDocumentChecklist: (...args: unknown[]) => createMock(...args),
  updateDocumentChecklist: (...args: unknown[]) => updateMock(...args),
  fetchDocumentChecklistSyncLogs: (...args: unknown[]) => syncLogsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function item(id: number, name: string): DocumentChecklistItem {
  return {
    id,
    name,
    description: null,
    section: null,
    is_required: true,
    allowed_file_types: ['pdf'],
    max_file_size_kb: 10240,
    min_files: 1,
    max_files: 1,
    validation_prompt: null,
    max_age_days: null,
  }
}

function checklist(overrides: Partial<DocumentChecklistWithPermissions> = {}): DocumentChecklistWithPermissions {
  return {
    id: 5,
    name: 'Onboarding',
    code: 'ONB',
    description: null,
    is_active: true,
    code_locked: false,
    sync_status: 'synced',
    sync_error: null,
    synced_at: '2026-11-01T10:00:00Z',
    items: [item(11, 'ID card'), item(12, 'Tax code')],
    created_at: '2026-11-01T10:00:00Z',
    updated_at: '2026-11-01T10:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

const saveButton = () => screen.getAllByRole('button', { name: i18n.t('documentChecklists.form.save') })[0]
const nameBoxes = () => screen.getAllByRole('textbox', { name: /^Name/ })

function validationError(errors: Record<string, string[]>): AxiosError {
  const response = { status: 422, data: { message: 'Invalid', errors } } as AxiosResponse
  return new AxiosError('Unprocessable', 'ERR_BAD_REQUEST', undefined, undefined, response)
}

function renderEdit(overrides: Partial<DocumentChecklistWithPermissions> = {}) {
  return render(<DocumentChecklistForm checklist={checklist(overrides)} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: wrapper(),
  })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
  syncLogsMock.mockReset()
  syncLogsMock.mockResolvedValue([])
})

describe('DocumentChecklistForm - create (spec 0403 AC-024)', () => {
  it('adds, fills and reorders entries and sends them in the order shown', async () => {
    createMock.mockResolvedValue(checklist())
    const onSuccess = vi.fn()
    render(<DocumentChecklistForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.change(nameBoxes()[0], { target: { value: 'Onboarding' } })
    fireEvent.change(screen.getByRole('textbox', { name: /^Code/ }), { target: { value: 'onb' } })
    const add = screen.getByRole('button', { name: i18n.t('documentChecklists.actions.addItem') })
    fireEvent.click(add)
    fireEvent.click(add)
    fireEvent.change(nameBoxes()[1], { target: { value: 'First' } })
    fireEvent.change(nameBoxes()[2], { target: { value: 'Second' } })

    fireEvent.click(screen.getByRole('button', { name: 'Move up: Item 2' }))
    fireEvent.click(saveButton())

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const payload = createMock.mock.calls[0][0]
    expect(payload.code).toBe('ONB')
    expect(payload.items.map((entry: { name: string }) => entry.name)).toEqual(['Second', 'First'])
    expect(payload.items.every((entry: { id: number | null }) => entry.id === null)).toBe(true)
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('removes an entry and has no synchronization section', () => {
    render(<DocumentChecklistForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('documentChecklists.actions.addItem') }))
    expect(screen.getByText('Item 1')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Remove item: Item 1' }))

    expect(screen.queryByText('Item 1')).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: i18n.t('documentChecklists.syncLogs.title') })).not.toBeInTheDocument()
  })

  it('blocks submit without a name and wires the aria triad', async () => {
    render(<DocumentChecklistForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(saveButton())

    const message = await screen.findByText(i18n.t('documentChecklists.validation.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    const input = nameBoxes()[0]
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(message.id)
    expect(createMock).not.toHaveBeenCalled()
  })
})

describe('DocumentChecklistForm - edit (spec 0403 AC-024, AC-027)', () => {
  it('keeps the code read-only once locked and shows the reason', () => {
    renderEdit({ code_locked: true })

    expect(screen.getByRole('textbox', { name: /^Code/ })).toHaveAttribute('readonly')
    expect(screen.getByText(i18n.t('documentChecklists.form.codeLocked'))).toBeInTheDocument()
  })

  it('keeps existing entry ids and puts a server 422 on the entry field', async () => {
    updateMock.mockRejectedValue(validationError({ 'items.1.name': ['The entry name is taken.'] }))
    renderEdit()

    fireEvent.click(saveButton())

    const message = await screen.findByText('The entry name is taken.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(nameBoxes()[2]).toHaveAttribute('aria-invalid', 'true')
    expect(updateMock.mock.calls[0][0]).toBe(5)
    expect(updateMock.mock.calls[0][1].items.map((entry: { id: number }) => entry.id)).toEqual([11, 12])
  })

  it('lists the latest synchronizations with date, operation, outcome, HTTP code and message', async () => {
    const logs: DocumentChecklistSyncLog[] = [
      { id: 1, operation: 'upsert', outcome: 'failed', http_status: 500, attempt: 1, message: 'Boom', created_at: '2026-11-02T10:00:00Z' },
      { id: 2, operation: 'delete', outcome: 'retrying', http_status: null, attempt: 2, message: null, created_at: '2026-11-01T10:00:00Z' },
    ]
    syncLogsMock.mockResolvedValue(logs)
    renderEdit({ sync_status: 'failed', sync_error: 'Remote down' })

    const rows = await screen.findAllByRole('row')
    expect(rows).toHaveLength(3)
    expect(within(rows[1]).getByText('500')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Boom')).toBeInTheDocument()
    expect(within(rows[2]).getAllByText('—')).toHaveLength(2)
    expect(screen.getByRole('alert', { name: '' })).toHaveTextContent('Remote down')
    expect(screen.getAllByText(i18n.t('documentChecklists.syncStatus.failed'))[0]).toBeInTheDocument()
  })

  it('shows the empty state when there are no calls', async () => {
    renderEdit()

    expect(await screen.findByText(i18n.t('documentChecklists.syncLogs.empty'))).toBeInTheDocument()
  })
})
