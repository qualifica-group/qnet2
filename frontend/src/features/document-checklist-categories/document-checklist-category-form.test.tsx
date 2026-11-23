import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { DocumentChecklistCategoryForm } from '@/features/document-checklist-categories/document-checklist-category-form'
import type { DocumentChecklistCategoryWithPermissions } from '@/features/document-checklist-categories/types'
import type { DocumentChecklistSyncLog } from '@/features/document-checklists/types'

const createMock = vi.fn()
const updateMock = vi.fn()
const syncLogsMock = vi.fn()

vi.mock('@/features/document-checklist-categories/api', () => ({
  createDocumentChecklistCategory: (...args: unknown[]) => createMock(...args),
  updateDocumentChecklistCategory: (...args: unknown[]) => updateMock(...args),
  fetchDocumentChecklistCategorySyncLogs: (...args: unknown[]) => syncLogsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function category(overrides: Partial<DocumentChecklistCategoryWithPermissions> = {}): DocumentChecklistCategoryWithPermissions {
  return {
    id: 3,
    name: 'Mortgages',
    description: null,
    sort_order: 2,
    checklists_count: 0,
    sync_status: 'synced',
    sync_error: null,
    synced_at: '2026-11-01T10:00:00Z',
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

function validationError(errors: Record<string, string[]>): AxiosError {
  const response = { status: 422, data: { message: 'Invalid', errors } } as AxiosResponse
  return new AxiosError('Unprocessable', 'ERR_BAD_REQUEST', undefined, undefined, response)
}

const saveButton = () => screen.getAllByRole('button', { name: i18n.t('documentChecklistCategories.form.save') })[0]
const nameBox = () => screen.getByRole('textbox', { name: /^Name/ })

function renderEdit(overrides: Partial<DocumentChecklistCategoryWithPermissions> = {}) {
  return render(<DocumentChecklistCategoryForm category={category(overrides)} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
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

describe('DocumentChecklistCategoryForm - create (spec 0410 AC-018)', () => {
  it('sends the trimmed values with sort_order 0 by default and null for an empty description', async () => {
    createMock.mockResolvedValue(category())
    const onSuccess = vi.fn()
    render(<DocumentChecklistCategoryForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.change(nameBox(), { target: { value: ' Mortgages ' } })
    fireEvent.click(saveButton())

    await waitFor(() => expect(createMock).toHaveBeenCalledWith({ name: 'Mortgages', description: null, sort_order: 0 }))
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
    expect(screen.queryByRole('heading', { name: i18n.t('documentChecklists.syncLogs.title') })).not.toBeInTheDocument()
  })

  it('blocks submit without a name and wires the aria triad', async () => {
    render(<DocumentChecklistCategoryForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.click(saveButton())

    const message = await screen.findByText(i18n.t('documentChecklistCategories.validation.nameRequired'))
    expect(message).toHaveAttribute('role', 'alert')
    expect(nameBox()).toHaveAttribute('aria-invalid', 'true')
    expect(nameBox().getAttribute('aria-describedby')).toContain(message.id)
    expect(createMock).not.toHaveBeenCalled()
  })

  it('blocks an out-of-range sort order', async () => {
    render(<DocumentChecklistCategoryForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.change(nameBox(), { target: { value: 'Mortgages' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: /^Order/ }), { target: { value: '65536' } })
    fireEvent.click(saveButton())

    expect(await screen.findByText(i18n.t('documentChecklistCategories.validation.sortOrderRange'))).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })

  it('puts the server 422 on name (name already in use) on the field', async () => {
    createMock.mockRejectedValue(validationError({ name: ['The name is already in use.'] }))
    render(<DocumentChecklistCategoryForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.change(nameBox(), { target: { value: 'Mortgages' } })
    fireEvent.click(saveButton())

    const message = await screen.findByText('The name is already in use.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(nameBox()).toHaveAttribute('aria-invalid', 'true')
  })
})

describe('DocumentChecklistCategoryForm - detail (spec 0410 AC-019)', () => {
  it('saves in place with the category id', async () => {
    updateMock.mockResolvedValue(category())
    renderEdit()

    fireEvent.change(nameBox(), { target: { value: 'Loans' } })
    fireEvent.click(saveButton())

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(3, { name: 'Loans', description: null, sort_order: 2 }))
  })

  it('lists the latest synchronizations with the current error', async () => {
    const logs: DocumentChecklistSyncLog[] = [
      { id: 1, operation: 'upsert', outcome: 'failed', http_status: 422, attempt: 1, message: 'Name taken', created_at: '2026-11-02T10:00:00Z' },
    ]
    syncLogsMock.mockResolvedValue(logs)
    renderEdit({ sync_status: 'failed', sync_error: 'Remote refused' })

    const rows = await screen.findAllByRole('row')
    expect(rows).toHaveLength(2)
    expect(within(rows[1]).getByText('422')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Name taken')).toBeInTheDocument()
    expect(screen.getByRole('alert', { name: '' })).toHaveTextContent('Remote refused')
    expect(syncLogsMock).toHaveBeenCalled()
  })

  it('shows the empty state when there are no calls', async () => {
    renderEdit()

    expect(await screen.findByText(i18n.t('documentChecklists.syncLogs.empty'))).toBeInTheDocument()
  })

  it('disables the fields for a viewer without update rights', () => {
    render(<DocumentChecklistCategoryForm category={category()} canEdit={false} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(nameBox()).toBeDisabled()
  })
})
