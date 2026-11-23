import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { DocumentChecklistForm } from '@/features/document-checklists/document-checklist-form'
import type { DocumentChecklistWithPermissions } from '@/features/document-checklists/types'

const createMock = vi.fn()
const updateMock = vi.fn()

vi.mock('@/features/document-checklists/api', () => ({
  createDocumentChecklist: (...args: unknown[]) => createMock(...args),
  updateDocumentChecklist: (...args: unknown[]) => updateMock(...args),
  fetchDocumentChecklistSyncLogs: () => Promise.resolve([]),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function saved(overrides: Partial<DocumentChecklistWithPermissions> = {}): DocumentChecklistWithPermissions {
  return {
    id: 5,
    name: 'Onboarding',
    code: 'ONB',
    description: null,
    category: null,
    is_active: true,
    code_locked: false,
    sync_status: 'synced',
    sync_error: null,
    synced_at: null,
    sections: [],
    items: [],
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

const entry = {
  id: 11,
  name: 'ID card',
  description: null,
  section_index: null,
  date_field: null,
  is_required: true,
  allowed_file_types: ['pdf' as const],
  max_file_size_kb: 10240,
  min_files: 1,
  max_files: 1,
  validation_prompt: null,
  max_age_days: null,
}

const click = (name: string) => fireEvent.click(screen.getByRole('button', { name }))
const saveButton = () => screen.getAllByRole('button', { name: i18n.t('documentChecklists.form.save') })[0]
const groupSelect = () => screen.getByRole('combobox', { name: 'Group' })

async function optionNames(): Promise<string[]> {
  fireEvent.keyDown(groupSelect(), { key: 'ArrowDown' })
  const options = await screen.findAllByRole('option')
  const names = options.map((option) => option.textContent ?? '')
  fireEvent.keyDown(screen.getByRole('listbox'), { key: 'Escape' })
  return names
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMock.mockReset()
  updateMock.mockReset()
})

describe('DocumentChecklistForm - groups (spec 0427 AC-007)', () => {
  it('adds, renames and moves groups; the select follows the order and the payload is coherent', async () => {
    createMock.mockResolvedValue(saved())
    render(<DocumentChecklistForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })

    fireEvent.change(screen.getAllByRole('textbox', { name: /^Name/ })[0], { target: { value: 'Onboarding' } })
    fireEvent.change(screen.getByRole('textbox', { name: /^Code/ }), { target: { value: 'onb' } })
    click('New group')
    click('New group')
    const names = screen.getAllByRole('textbox', { name: /^Group name/ })
    fireEvent.change(names[0], { target: { value: 'Identity' } })
    fireEvent.change(names[1], { target: { value: 'Property' } })
    click('Add item')
    fireEvent.change(screen.getAllByRole('textbox', { name: /^Name/ })[1], { target: { value: 'Deed' } })

    expect(await optionNames()).toEqual(['No group (Other documents)', 'Identity', 'Property'])

    click('Move up: Group 2')
    expect(await optionNames()).toEqual(['No group (Other documents)', 'Property', 'Identity'])

    fireEvent.keyDown(groupSelect(), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Property' }))
    fireEvent.click(saveButton())

    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    const payload = createMock.mock.calls[0][0]
    expect(payload.sections.map((section: { name: string }) => section.name)).toEqual(['Property', 'Identity'])
    expect(payload.items[0].section_index).toBe(0)
    expect(payload.items[0]).not.toHaveProperty('section')
  })

  it('sends the items of a deleted group back to "No group"', async () => {
    updateMock.mockResolvedValue(saved())
    render(
      <DocumentChecklistForm
        checklist={saved({ sections: [{ id: 3, name: 'Identity', description: null }], items: [{ ...entry, section_index: 0 }] })}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )
    expect(groupSelect()).toHaveTextContent('Identity')

    click('Remove group: Group 1')

    expect(groupSelect()).toHaveTextContent('No group (Other documents)')
    fireEvent.click(saveButton())
    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    expect(updateMock.mock.calls[0][1].sections).toEqual([])
    expect(updateMock.mock.calls[0][1].items[0].section_index).toBeNull()
  })

  it('disables adding past 20 groups and shows the hint', () => {
    const sections = Array.from({ length: 20 }, (_, index) => ({ id: index + 1, name: `G${index}`, description: null }))
    render(<DocumentChecklistForm checklist={saved({ sections })} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    expect(screen.getByRole('button', { name: 'New group' })).toBeDisabled()
    expect(screen.getByText('Maximum of 20 groups reached.')).toBeInTheDocument()
  })
})

describe('DocumentChecklistForm - item rules (spec 0427 AC-008, AC-009)', () => {
  it('opens an item with max_files 3 with "More documents" checked', () => {
    render(
      <DocumentChecklistForm checklist={saved({ items: [{ ...entry, max_files: 3 }] })} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('checkbox', { name: 'More documents' })).toBeChecked()
  })

  it('enables the extracted date field only with a maximum age and flags a non snake_case value', async () => {
    updateMock.mockResolvedValue(saved())
    render(<DocumentChecklistForm checklist={saved({ items: [entry] })} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })
    const dateField = screen.getByRole('textbox', { name: 'Extracted date field' })
    expect(dateField).toBeDisabled()

    fireEvent.change(screen.getByRole('spinbutton', { name: /^Maximum age/ }), { target: { value: '30' } })
    expect(dateField).toBeEnabled()
    fireEvent.change(dateField, { target: { value: 'Issue Date' } })
    fireEvent.click(saveButton())

    expect(await screen.findByText(i18n.t('documentChecklists.validation.dateFieldFormat'))).toHaveAttribute('role', 'alert')
    expect(updateMock).not.toHaveBeenCalled()
  })
})
