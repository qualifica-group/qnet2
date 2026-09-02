import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { CampaignsTable } from '@/features/campaigns/campaigns-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'

/**
 * Spec 0025 Parte B, AC-024 (mirrors projects AC-020/021/022/023) — the
 * Campaigns adapter now opens a resizable Sheet for view/edit/create instead
 * of navigating to the dedicated pages (those remain as deep-links, covered
 * separately by the page tests). The generic `<TableView>` (AG Grid + SSRM)
 * is stubbed with buttons that fire `onAction` for a fixed row, mirroring the
 * sheet-based adapters' suites (e.g. `SectorsTable`, `ProjectsTable`).
 */

const mockCampaign: CampaignDetailWithPermissions = {
  id: 21,
  code: 'CMP-0021',
  project_id: null,
  project: null,
  name: 'Spring outreach',
  description: null,
  partner_id: null,
  partner: null,
  operational_site_id: null,
  operational_site: null,
  derived_from_project: false,
  pipeline_status_id: 1,
  pipeline_status: { id: 1, name: 'Active', color: null },
  country_id: null,
  country: null,
  state_id: null,
  state: null,
  province_id: null,
  province: null,
  city_id: null,
  city: null,
  geo_scope: null,
  geo_locked_levels: [],
  product_lines: [],
  start_date: null,
  end_date: null,
  total_budget: null,
  target_lead: null,
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

// Default modal behaviour; force the resolved open mode (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

const ROW: TableRow = { id: 21, actions: ['view', 'edit', 'delete'], name: 'Spring outreach' }

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
        </div>
      )
    },
  ),
}))

const fetchCampaignMock = vi.fn<() => Promise<CampaignDetailWithPermissions>>()
const deleteCampaignMock = vi.fn()

vi.mock('@/features/campaigns/api', () => ({
  fetchCampaign: () => fetchCampaignMock(),
  deleteCampaign: (...args: unknown[]) => deleteCampaignMock(...args),
  campaignDetailQueryKey: (id: number | null) => ['campaigns', 'detail', id],
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/**
 * `CampaignForm` submission is owned by a different lane and covered by its
 * own test suite. Here it is stubbed to two buttons that invoke
 * `onSuccess`/`onCancel` directly, so this suite can verify the table's own
 * responsibility: what happens to the Sheet and the grid once a create/edit
 * round-trips (AC-023).
 */
vi.mock('@/features/campaigns/campaign-form', () => ({
  CampaignForm: ({
    onSuccess,
    onCancel,
  }: {
    onSuccess: (campaign: CampaignDetailWithPermissions) => void
    onCancel: () => void
  }) => (
    <div>
      <button type="button" onClick={() => onSuccess(mockCampaign)}>
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
  return {
    client,
    ...render(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <CampaignsTable />
        </MemoryRouter>
      </QueryClientProvider>,
    ),
  }
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
  fetchCampaignMock.mockReset()
  fetchCampaignMock.mockResolvedValue(mockCampaign)
  deleteCampaignMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('CampaignsTable — Sheet-based CRUD (AC-024)', () => {
  it('mounts <TableView domain="campaigns">', () => {
    renderTable()

    expect(screen.getByRole('region', { name: 'table-campaigns' })).toBeInTheDocument()
    expect(capturedOnAction).not.toBeNull()
  })

  it('opens the view sheet with the campaign detail on the view action, without navigating', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-view'))

    await waitFor(() => expect(fetchCampaignMock).toHaveBeenCalled())
    expect(await screen.findAllByText('Spring outreach')).not.toHaveLength(0)
  })

  it('opens the edit sheet on the edit action', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))

    expect(await screen.findByText('Edit campaign')).toBeInTheDocument()
  })

  it('opens the create sheet from the New campaign button', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: /new campaign/i }))

    expect(await screen.findByText('Create campaign')).toBeInTheDocument()
  })

  it('hides the New campaign button without campaigns.create', () => {
    canMock.mockImplementation((permission) => permission !== 'campaigns.create')

    renderTable()

    expect(screen.queryByRole('button', { name: /new campaign/i })).not.toBeInTheDocument()
  })

  it('deletes the row, refreshes the grid and shows a success toast', async () => {
    deleteCampaignMock.mockResolvedValue(undefined)
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() => expect(deleteCampaignMock).toHaveBeenCalledWith(21))
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(toast.success).toHaveBeenCalledWith('Campaign deleted successfully.')
  })

  it('maps a 403 delete failure to the forbidden message', async () => {
    deleteCampaignMock.mockRejectedValue(axiosErrorWithStatus(403))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('You cannot delete this campaign.'),
    )
  })

  it('maps any other delete failure to the generic error message', async () => {
    deleteCampaignMock.mockRejectedValue(axiosErrorWithStatus(500))
    renderTable()

    fireEvent.click(screen.getByText('trigger-delete'))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to delete the campaign. Please try again.'),
    )
  })
})

describe('CampaignsTable — mutation success closes the sheet and refreshes (AC-023)', () => {
  it('closes the create sheet, refreshes the grid and invalidates the detail query on save', async () => {
    const { client } = renderTable()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    fireEvent.click(screen.getByRole('button', { name: /new campaign/i }))
    expect(await screen.findByText('Create campaign')).toBeInTheDocument()

    fireEvent.click(screen.getByText('stub-save'))

    await waitFor(() => expect(screen.queryByText('Create campaign')).not.toBeInTheDocument())
    expect(refreshMock).toHaveBeenCalled()
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['campaigns', 'detail', 21] })
  })

  it('closes the edit sheet on cancel without refreshing the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByText('trigger-edit'))
    expect(await screen.findByText('Edit campaign')).toBeInTheDocument()

    fireEvent.click(await screen.findByText('stub-cancel'))

    await waitFor(() => expect(screen.queryByText('Edit campaign')).not.toBeInTheDocument())
    expect(refreshMock).not.toHaveBeenCalled()
  })
})
