import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SectorForm } from '@/features/sectors/sector-form'
import type { SectorDetailWithPermissions } from '@/features/sectors/types'
import type { SectorTreeNode } from '@/features/sectors/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0018 AC-017/AC-018: metadata-driven form fields, parent picker anti-cycle exclusion. */

const createSectorMock = vi.fn()
const updateSectorMock = vi.fn()
const fetchSectorTreeMock = vi.fn<() => Promise<SectorTreeNode[]>>()

vi.mock('@/features/sectors/api', () => ({
  createSector: (...args: unknown[]) => createSectorMock(...args),
  updateSector: (...args: unknown[]) => updateSectorMock(...args),
  fetchSectorTree: () => fetchSectorTreeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/**
 * Not about authorization metadata: every field resolves as visible+editable
 * (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/sectors/use-sector-form-meta', () => ({
  useSectorFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useSectorForm` reads `/meta/sectors` (spec 0021) to build the dynamic
// custom-fields schema; this suite has no custom fields to exercise (covered
// by `sector-form-custom-fields.test.tsx`), so it resolves to an empty catalogue.
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => Promise.resolve({ fields: [], permissions: FULL_ACCESS_PERMISSIONS }),
}))

/** Root A > Child A1 > Grandchild A1a, plus a second root sibling. */
const TREE: SectorTreeNode[] = [
  {
    id: 1,
    name: 'Root A',
    parent_id: null,
    is_active: true,
    children: [
      {
        id: 2,
        name: 'Child A1',
        parent_id: 1,
        is_active: true,
        children: [{ id: 3, name: 'Grandchild A1a', parent_id: 2, is_active: true, children: [] }],
      },
    ],
  },
  { id: 4, name: 'Root B', parent_id: null, is_active: true, children: [] },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function sector(
  overrides: Partial<SectorDetailWithPermissions> = {},
): SectorDetailWithPermissions {
  return {
    id: 2,
    code: null,
    name: 'Child A1',
    parent_id: 1,
    parent: { id: 1, name: 'Root A' },
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

function openParentPicker() {
  fireEvent.click(screen.getByRole('combobox'))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createSectorMock.mockReset()
  updateSectorMock.mockReset()
  fetchSectorTreeMock.mockReset()
  fetchSectorTreeMock.mockResolvedValue(TREE)
})

describe('SectorForm — create/edit (AC-017)', () => {
  it('renders the name field and the parent picker in create mode', async () => {
    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument()
    expect(screen.getByRole('combobox')).toBeInTheDocument()

    openParentPicker()
    expect(await screen.findByRole('option', { name: 'No parent (root sector)' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root A' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root B' })).toBeInTheDocument()
  })

  it('shows a validation error when name is empty on submit', async () => {
    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Name is required.')).toBeInTheDocument()
    expect(createSectorMock).not.toHaveBeenCalled()
  })

  it('shows a validation error when name exceeds 191 characters', async () => {
    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'a'.repeat(192) } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Name must be at most 191 characters.')).toBeInTheDocument()
    expect(createSectorMock).not.toHaveBeenCalled()
  })

  it('submits the create payload with parent_id null as root', async () => {
    createSectorMock.mockResolvedValue(sector({ id: 5, name: 'New Sector', parent_id: null, parent: null }))
    const onSuccess = vi.fn()

    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'New Sector' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createSectorMock).toHaveBeenCalledTimes(1))
    expect(createSectorMock).toHaveBeenCalledWith({
      code: null,
      name: 'New Sector',
      parent_id: null,
      is_active: true,
    })
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('hydrates name and parent in edit mode', () => {
    render(
      <SectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Name/)).toHaveValue('Child A1')
  })
})

describe('SectorForm — parent picker anti-cycle exclusion (AC-018)', () => {
  it('excludes the edited node and its subtree from the parent options', async () => {
    render(
      <SectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    openParentPicker()

    expect(await screen.findByRole('option', { name: 'Root A' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Root B' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Child A1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Grandchild A1a/ })).not.toBeInTheDocument()
  })

  it('submits only the changed field on a partial update', async () => {
    updateSectorMock.mockResolvedValue(sector({ name: 'Renamed' }))

    render(
      <SectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateSectorMock.mock.calls[0]
    expect(id).toBe(2)
    expect(payload).toEqual({ name: 'Renamed' })
  })
})

describe('SectorForm — active flag (spec 0212 AC-006)', () => {
  it('defaults the Active switch to on in create mode', async () => {
    render(
      <SectorForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('switch', { name: /Active/ })).toBeChecked()
  })

  it('names the inactive ancestor that deactivates the branch', async () => {
    fetchSectorTreeMock.mockResolvedValue([{ ...TREE[0], is_active: false }, TREE[1]])

    render(
      <SectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByText('Not active because "Root A" is not')).toBeInTheDocument()
  })

  it('submits only is_active when the switch is turned off', async () => {
    updateSectorMock.mockResolvedValue(sector({ is_active: false }))

    render(
      <SectorForm mode={{ type: 'edit', sector: sector() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('switch', { name: /Active/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledTimes(1))
    expect(updateSectorMock.mock.calls[0][1]).toEqual({ is_active: false })
  })
})
