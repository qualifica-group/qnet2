import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { SectorDetailView } from '@/features/sectors/sector-detail'
import type { SectorDetailWithPermissions, SectorTreeNode } from '@/features/sectors/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'

/**
 * Spec 0237: the detail shows the name (hero title), code, parent, active
 * flag, custom fields and creation date; every row edits in place (wave 3 of
 * the "no edit pages" plan), each save a PATCH of that field alone. The
 * Activity Log gate is exercised on the `permissions.actions.view_activity`
 * prop.
 */

const activityLogSectionMock = vi.fn()
const updateSectorMock = vi.fn()
const fetchSectorTreeMock = vi.fn<() => Promise<SectorTreeNode[]>>()

vi.mock('@/features/sectors/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/sectors/api')>()),
  updateSector: (...args: unknown[]) => updateSectorMock(...args),
  fetchSectorTree: () => fetchSectorTreeMock(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

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

function permissions(overrides: Partial<ResourcePermissions> = {}): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: { 'custom.notes': EDITABLE },
    actions: { view_activity: false },
    ...overrides,
  }
}

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

function sector(overrides: Partial<SectorDetailWithPermissions> = {}): SectorDetailWithPermissions {
  return {
    id: 2,
    code: '07a',
    name: 'Child A1',
    parent_id: 1,
    parent: { id: 1, name: 'Root A' },
    is_active: true,
    created_at: '2026-01-01T09:00:00Z',
    custom_fields: { notes: 'Existing note' },
    permissions: permissions(),
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const label = (key: string) => i18n.t(`sectors.form.${key}`)
const pencilOf = (field: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field }) })
const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  updateSectorMock.mockReset()
  fetchSectorTreeMock.mockReset()
  fetchSectorTreeMock.mockResolvedValue(TREE)
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [NOTES_FIELD], permissions: permissions() })
})

describe('SectorDetailView — detail fields', () => {
  it('shows the name, code, parent, custom field and creation date', async () => {
    render(<SectorDetailView sector={sector()} />)

    expect(screen.getByRole('heading', { name: 'Child A1' })).toBeInTheDocument()
    expect(screen.getAllByText('07a').length).toBeGreaterThan(0)
    expect(await screen.findByText('Existing note')).toBeInTheDocument()
    expect((await screen.findAllByText('Root A')).length).toBeGreaterThan(0)
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
  })

  it('flags a not active sector in the header', () => {
    render(<SectorDetailView sector={sector({ is_active: false })} />)

    expect(screen.getByText('Not active')).toBeInTheDocument()
  })
})

describe('SectorDetailView — activity log section', () => {
  it('mounts the section for the viewed row when view_activity is granted', () => {
    render(<SectorDetailView sector={sector({ permissions: permissions({ actions: { view_activity: true } }) })} />)

    expect(screen.getByText('Activity log')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'sectors', id: 2 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<SectorDetailView sector={sector()} />)

    expect(screen.queryByText('Activity log')).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})

describe('SectorDetailView — in-place editing', () => {
  it('has no Edit button: every row is closed, with its pencil', async () => {
    render(<SectorDetailView sector={sector()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    for (const key of ['code', 'name', 'parent', 'isActive']) {
      expect(pencilOf(label(key))).toBeInTheDocument()
    }
    expect(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })).toBeInTheDocument()
  })

  it('Save sends only the changed field', async () => {
    updateSectorMock.mockResolvedValue(sector({ name: 'Renamed' }))
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(pencilOf(label('name')) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    saveRow()

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledWith(2, { name: 'Renamed' }))
  })

  it('a blank code is sent as null', async () => {
    updateSectorMock.mockResolvedValue(sector({ code: null }))
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(pencilOf(label('code')) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: '' } })
    saveRow()

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledWith(2, { code: null }))
  })

  it('the parent row excludes the edited node and its subtree, and saves only parent_id', async () => {
    updateSectorMock.mockResolvedValue(sector({ parent_id: 4, parent: { id: 4, name: 'Root B' } }))
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(pencilOf(label('parent')) as HTMLElement)
    fireEvent.click(screen.getByRole('combobox'))

    expect(await screen.findByRole('option', { name: 'Root A' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Child A1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Grandchild A1a/ })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('option', { name: 'Root B' }))
    saveRow()

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledWith(2, { parent_id: 4 }))
  })

  it('the active row saves only is_active', async () => {
    updateSectorMock.mockResolvedValue(sector({ is_active: false }))
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(pencilOf(label('isActive')) as HTMLElement)
    fireEvent.click(screen.getByRole('switch', { name: /Active/ }))
    saveRow()

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledWith(2, { is_active: false }))
  })

  it('names the inactive ancestor that deactivates the branch (spec 0212)', async () => {
    fetchSectorTreeMock.mockResolvedValue([{ ...TREE[0], is_active: false }, TREE[1]])
    render(<SectorDetailView sector={sector()} />)

    expect(await screen.findByText('Not active because "Root A" is not')).toBeInTheDocument()
  })

  it('a custom field row saves only that custom field', async () => {
    updateSectorMock.mockResolvedValue(sector({ custom_fields: { notes: 'Updated note' } }))
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Updated note' } })
    saveRow()

    await waitFor(() => expect(updateSectorMock).toHaveBeenCalledWith(2, { custom_fields: { notes: 'Updated note' } }))
  })

  it('Cancel restores the row without calling the API', () => {
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(pencilOf(label('name')) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateSectorMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open row', async () => {
    updateSectorMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { name: ['Name already taken.'] } },
      } as never),
    )
    render(<SectorDetailView sector={sector()} />)

    fireEvent.click(pencilOf(label('name')) as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Duplicate' } })
    saveRow()

    expect(await screen.findByText('Name already taken.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed', async () => {
    render(
      <SectorDetailView
        sector={sector({
          permissions: permissions({
            resource: { view: true, create: true, update: false, delete: true, export: true, import: true },
            fields: { 'custom.notes': { ...EDITABLE, editable: false, readonly: true } },
          }),
        })}
      />,
    )

    for (const key of ['code', 'name', 'parent', 'isActive']) {
      expect(pencilOf(label(key))).not.toBeInTheDocument()
    }
    await screen.findByText('Existing note')
    expect(pencilOf('Notes')).not.toBeInTheDocument()
  })
})
