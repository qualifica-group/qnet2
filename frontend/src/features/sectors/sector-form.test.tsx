import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SectorForm } from '@/features/sectors/sector-form'
import type { SectorDetailWithPermissions, SectorTreeNode } from '@/features/sectors/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0237: the sector form is create-only, a replica of the detail with its
 * rows closed (the in-place edits live in `sector-detail.test.tsx`). Covers
 * the parent picker (spec 0018), the active flag default (spec 0212), the
 * metadata-driven behaviour (spec 0004) and the custom fields (spec 0021).
 */

const createSectorMock = vi.fn()
const fetchSectorTreeMock = vi.fn<() => Promise<SectorTreeNode[]>>()

vi.mock('@/features/sectors/api', () => ({
  createSector: (...args: unknown[]) => createSectorMock(...args),
  fetchSectorTree: () => fetchSectorTreeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const NOTES_FIELD: CustomFieldDescriptor = {
  key: 'custom.notes',
  type: 'text',
  label: 'Notes',
  group: null,
  mandatory: false,
  source: 'custom',
}

const EDITABLE = { visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false }

function permissions(fields: ResourcePermissions['fields'] = {}): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: { 'custom.notes': EDITABLE, ...fields },
    actions: {},
  }
}

/** Root A > Child A1, plus a second root sibling. */
const TREE: SectorTreeNode[] = [
  {
    id: 1,
    code: null,
    name: 'Root A',
    parent_id: null,
    is_active: true,
    children: [{ id: 2, code: null, name: 'Child A1', parent_id: 1, is_active: true, children: [] }],
  },
  { id: 4, code: null, name: 'Root B', parent_id: null, is_active: true, children: [] },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function sector(): SectorDetailWithPermissions {
  return {
    id: 5,
    code: null,
    name: 'New Sector',
    parent_id: null,
    parent: null,
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissions(),
  }
}

/** Opens a closed row through its pencil. */
function openRow(label: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
}

const label = (key: string) => i18n.t(`sectors.form.${key}`)
const save = () => fireEvent.click(screen.getAllByRole('button', { name: label('save') })[0])

async function renderForm(onSuccess = vi.fn()) {
  render(<SectorForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })
  await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createSectorMock.mockReset()
  fetchSectorTreeMock.mockReset()
  fetchSectorTreeMock.mockResolvedValue(TREE)
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('SectorForm — create (spec 0237)', () => {
  it('starts with every row closed; the name row opens on its own control', async () => {
    await renderForm()

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()

    openRow(label('name'))
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
  })

  it('offers the sector tree, with the root option, in the parent row', async () => {
    await renderForm()

    openRow(label('parent'))
    fireEvent.click(screen.getByRole('combobox'))

    expect(await screen.findByRole('option', { name: 'No parent (root sector)' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root A' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root B' })).toBeInTheDocument()
  })

  it('shows the error on the closed row and does not call the API when name is empty', async () => {
    await renderForm()

    save()

    expect(await screen.findByText('Name is required.')).toHaveAttribute('role', 'alert')
    expect(createSectorMock).not.toHaveBeenCalled()
  })

  it('shows a validation error when name exceeds 191 characters', async () => {
    await renderForm()

    openRow(label('name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'a'.repeat(192) } })
    save()

    expect(await screen.findByText('Name must be at most 191 characters.')).toBeInTheDocument()
    expect(createSectorMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with parent_id null as root and the sector active', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissions() })
    createSectorMock.mockResolvedValue(sector())
    const onSuccess = vi.fn()
    render(<SectorForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })
    await screen.findAllByRole('button', { name: label('save') })

    openRow(label('name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'New Sector' } })
    save()

    await waitFor(() => expect(createSectorMock).toHaveBeenCalledTimes(1))
    expect(createSectorMock).toHaveBeenCalledWith({
      code: null,
      name: 'New Sector',
      parent_id: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(sector()))
  })

  it('creates under the picked parent', async () => {
    createSectorMock.mockResolvedValue(sector())
    await renderForm()

    openRow(label('name'))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'New Sector' } })
    openRow(label('parent'))
    fireEvent.click(screen.getByRole('combobox'))
    fireEvent.click(await screen.findByRole('option', { name: 'Root B' }))
    openRow('Notes')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'A note' } })
    save()

    await waitFor(() => expect(createSectorMock).toHaveBeenCalledTimes(1))
    expect(createSectorMock.mock.calls[0][0]).toMatchObject({
      name: 'New Sector',
      parent_id: 4,
      custom_fields: { notes: 'A note' },
    })
  })

  it('defaults the Active switch to on', async () => {
    await renderForm()

    openRow(label('isActive'))

    expect(screen.getByRole('switch', { name: /Active/ })).toBeChecked()
  })

  it('hides a hidden field from the create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissions({
        code: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
      }),
    })
    render(<SectorForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper: wrapper() })
    await screen.findAllByRole('button', { name: label('save') })

    expect(screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label('code') }) })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label('name') }) })).toBeInTheDocument()
  })
})
