import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ProjectsTable } from '@/features/projects/projects-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'

/**
 * Spec 0025 Parte B, AC-020/021/022/023 — the Projects adapter now opens a
 * resizable Sheet for view/edit/create instead of navigating to the dedicated
 * pages (those remain as deep-links, covered separately by the page tests).
 * The generic `<TableView>` (AG Grid + SSRM) is stubbed with buttons that fire
 * `onAction` for a fixed row, mirroring the sheet-based adapters' suites
 * (e.g. `SectorsTable`).
 */

const mockProject: ProjectDetailWithPermissions = {
  id: 12,
  code: 'PRJ-0012',
  name: 'Acme rollout',
  description: null,
  pipeline_status_id: 1,
  pipeline_status: { id: 1, name: 'Active', color: null },
  country_id: 1,
  country: { id: 1, name: 'Italy' },
  state_id: null,
  state: null,
  province_id: null,
  province: null,
  city_id: null,
  city: null,
  geo_scope: 'country',
  product_lines: [],
  partner_id: null,
  partner: null,
  operational_site_id: null,
  operational_site: null,
  start_date: null,
  end_date: null,
  total_budget: null,
  target_lead: null,
  allocated_budget: '0.00',
  remaining_budget: null,
  campaigns_count: 0,
  created_at: '2026-01-01T00:00:00Z',
  permissions: {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields: {},
    actions: {},
  },
}

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

// This suite exercises the default modal behaviour; force the resolved open
// mode so it never depends on an AuthProvider (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

const ROW: TableRow = { id: 12, actions: ['view', 'edit', 'delete', 'activity'], name: 'Acme rollout' }

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function action(key: string): TableActionDefinition {
  return {
    key,
    label: `actions.${key}`,
    icon: 'eye',
    type: key === 'delete' ? 'danger' : 'action',
    confirm: key === 'delete',
  }
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      capturedOnAction = onAction
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('view'), ROW)}>
            trigger-view
          </button>
          <button type="button" onClick={() => onAction(action('edit'), ROW)}>
            trigger-edit
          </button>
          <button type="button" onClick={() => onAction(action('delete'), ROW)}>
            trigger-delete
          </button>
          <button type="button" onClick={() => onAction(action('activity'), ROW)}>
            trigger-activity
          </button>
        </div>
      )
    },
  ),
}))

const fetchProjectMock = vi.fn<() => Promise<ProjectDetailWithPermissions>>()
const deleteProjectMock = vi.fn()

vi.mock('@/features/projects/api', () => ({
  fetchProject: () => fetchProjectMock(),
  deleteProject: (...args: unknown[]) => deleteProjectMock(...args),
  projectDetailQueryKey: (id: number | null) => ['projects', 'detail', id],
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/**
 * `ProjectForm` submission (RHF + Zod + the metadata-driven field catalogue)
 * is owned by a different lane and covered by its own test suite. Here it is
 * stubbed to two buttons that invoke `onSuccess`/`onCancel` directly, so this
 * suite can verify the table's own responsibility: what happens to the Sheet
 * and the grid once a create/edit round-trips (AC-023).
 */
vi.mock('@/features/projects/project-form', () => ({
  ProjectForm: ({
    onSuccess,
    onCancel,
  }: {
    onSuccess: (project: ProjectDetailWithPermissions) => void
    onCancel: () => void
  }) => (
    <div>
      <button type="button" onClick={() => onSuccess(mockProject)}>
        stub-save
      </button>
      <button type="button" onClick={onCancel}>
        stub-cancel
      </button>
    </div>
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return { client, ...render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ProjectsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  ) }
}

function axiosErrorWithStatus(status: number) {
  return new AxiosError('failed', String(status), undefined, undefined, {
    status,
    data: { success: false, message: 'error' },
  } as never)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  refreshMock.mockReset()
  capturedOnAction = null
  fetchProjectMock.mockReset()
  fetchProjectMock.mockResolvedValue(mockProject)
  deleteProjectMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
  activityLogSectionMock.mockReset()
})

describe('ProjectsTable — Sheet-based CRUD (AC-020/021/022)', () => {
  it('mounts <TableView domain="projects">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-projects' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('opens the view sheet with the project detail on the view action, without navigating', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(fetchProjectMock).toHaveBeenCalled())
    expect(await screen.findAllByText('Acme rollout')).not.toHaveLength(0)
  })

  it('opens the edit sheet on the edit action', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))

    expect(await screen.findByText('Edit project')).toBeInTheDocument()
  })

  it('opens the create sheet from the New project button', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: /new project/i }))

    expect(await screen.findByText('Create project')).toBeInTheDocument()
  })

  it('hides the New project button without projects.create', () => {
    canMock.mockImplementation((permission) => permission !== 'projects.create')

    renderTable()

    expect(screen.queryByRole('button', { name: /new project/i })).not.toBeInTheDocument()
  })

  it('deletes the row, refreshes the grid and shows a success toast', async () => {
    deleteProjectMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteProjectMock).toHaveBeenCalledWith(12))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toast.success).toHaveBeenCalledWith('Project deleted successfully.')
  })

  it('maps a 409 delete failure to the "has campaigns" message', async () => {
    deleteProjectMock.mockRejectedValue(axiosErrorWithStatus(409))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        'This project cannot be deleted: it still has campaigns linked to it.',
      ),
    )
  })

  it('maps a 403 delete failure to the forbidden message', async () => {
    deleteProjectMock.mockRejectedValue(axiosErrorWithStatus(403))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('You cannot delete this project.'),
    )
  })

  it('maps any other delete failure to the generic error message', async () => {
    deleteProjectMock.mockRejectedValue(axiosErrorWithStatus(500))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to delete the project. Please try again.'),
    )
  })
})

describe('ProjectsTable — mutation success closes the sheet and refreshes (AC-023)', () => {
  it('closes the create sheet, refreshes the grid and invalidates the detail query on save', async () => {
    const { client } = renderTable()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    fireEvent.click(screen.getByRole('button', { name: /new project/i }))
    expect(await screen.findByText('Create project')).toBeInTheDocument()

    fireEvent.click(screen.getByText('stub-save'))

    await waitFor(() => expect(screen.queryByText('Create project')).not.toBeInTheDocument())
    expect(refreshMock).toHaveBeenCalled()
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['projects', 'detail', 12] })
  })

  it('closes the edit sheet on cancel without refreshing the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))
    expect(await screen.findByText('Edit project')).toBeInTheDocument()

    fireEvent.click(await screen.findByText('stub-cancel'))

    await waitFor(() => expect(screen.queryByText('Edit project')).not.toBeInTheDocument())
    expect(refreshMock).not.toHaveBeenCalled()
  })
})

/** Spec 0034, AC-015: representative module for the activity log rollout beyond Users. */
describe('ProjectsTable — activity row action', () => {
  it('opens the activity dialog for the row and mounts the shared ActivityLogSection', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-activity'))

    expect(await screen.findByRole('dialog')).toBeInTheDocument()
    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'projects', id: 12 })
  })

  it('closes the dialog when it is dismissed', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-activity'))
    expect(await screen.findByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
