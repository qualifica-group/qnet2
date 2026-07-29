import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import MigrationsPage from '@/features/migrations/migrations-page'
import type {
  MigrationColumnsTemplate,
  MigrationPreviewPage,
  MigrationSourceSummary,
} from '@/features/migrations/types'

/**
 * Spec 0013 AC-020, template-first redesign: selecting a source renders the
 * EXPECTED field schema (`GET /migrations/{source}/columns`, no external
 * call) as the primary view; the read-only external preview
 * (`GET .../preview`) only fires once explicitly requested, and importing
 * opens the existing `ImportDialog`. The API module is mocked (same
 * convention as `company-form.test.tsx`); every assertion queries by
 * accessible role/text, never `data-testid`. The breadcrumb (router +
 * navigation query) is stubbed out entirely, matching `companies-table.test.tsx`.
 */

const fetchMigrationSourcesMock = vi.fn()
const fetchMigrationColumnsMock = vi.fn()
const fetchMigrationPreviewMock = vi.fn()
const startMigrationImportMock = vi.fn()
const fetchMigrationRunMock = vi.fn()
const fetchMigrationPlanMock = vi.fn()
const saveMigrationPlanMock = vi.fn()
const startMassMigrationMock = vi.fn()
const fetchMassMigrationRunMock = vi.fn()

vi.mock('@/features/migrations/api', () => ({
  fetchMigrationSources: (...args: unknown[]) => fetchMigrationSourcesMock(...args),
  fetchMigrationColumns: (...args: unknown[]) => fetchMigrationColumnsMock(...args),
  fetchMigrationPreview: (...args: unknown[]) => fetchMigrationPreviewMock(...args),
  startMigrationImport: (...args: unknown[]) => startMigrationImportMock(...args),
  fetchMigrationRun: (...args: unknown[]) => fetchMigrationRunMock(...args),
  fetchMigrationPlan: (...args: unknown[]) => fetchMigrationPlanMock(...args),
  saveMigrationPlan: (...args: unknown[]) => saveMigrationPlanMock(...args),
  startMassMigration: (...args: unknown[]) => startMassMigrationMock(...args),
  fetchMassMigrationRun: (...args: unknown[]) => fetchMassMigrationRunMock(...args),
}))

vi.mock('@/routes/breadcrumbs', () => ({
  AppBreadcrumbs: () => null,
}))

const SOURCES: MigrationSourceSummary[] = [
  { key: 'roles', label: 'Roles' },
  { key: 'users', label: 'Users' },
  { key: 'company-sites', label: 'Company sites' },
]

function previewPage(overrides: Partial<MigrationPreviewPage> = {}): MigrationPreviewPage {
  return {
    rows: [{ name: 'Editor' }, { name: 'Viewer' }],
    pagination: { page: 1, per_page: 20, total: 2, has_more: false },
    ...overrides,
  }
}

function columnsTemplate(overrides: Partial<MigrationColumnsTemplate> = {}): MigrationColumnsTemplate {
  return {
    columns: [{ id: 'name', label: 'Name', type: 'string' }],
    request: {
      method: 'GET',
      base_url: 'https://external-crm.test',
      path: 'roles',
      url: 'https://external-crm.test/roles',
    },
    sample: {
      data: [{ id: 1, name: 'Name' }],
      meta: { current_page: 1, per_page: 50, total: 1 },
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMigrationSourcesMock.mockReset().mockResolvedValue(SOURCES)
  fetchMigrationColumnsMock.mockReset().mockResolvedValue(columnsTemplate())
  fetchMigrationPreviewMock.mockReset().mockResolvedValue(previewPage())
  startMigrationImportMock.mockReset()
  fetchMigrationRunMock.mockReset()
  fetchMigrationPlanMock.mockReset().mockResolvedValue({
    sources: [
      { source: 'roles', label: 'Roles', enabled: true },
      { source: 'users', label: 'Users', enabled: true },
    ],
  })
  saveMigrationPlanMock.mockReset()
  startMassMigrationMock.mockReset()
  fetchMassMigrationRunMock.mockReset()
  Object.assign(navigator, { clipboard: { writeText: vi.fn().mockResolvedValue(undefined) } })
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

async function selectRolesSource() {
  const select = await screen.findByRole('combobox', { name: 'Source' })
  fireEvent.click(select)
  fireEvent.click(await screen.findByRole('option', { name: 'Roles' }))
}

describe('MigrationsPage', () => {
  it('translates the company-sites option in the Italian source select', async () => {
    await i18n.changeLanguage('it')

    try {
      render(<MigrationsPage />, { wrapper: wrapper() })

      const select = await screen.findByRole('combobox', { name: 'Sorgente' })
      fireEvent.click(select)

      expect(await screen.findByRole('option', { name: 'Società sedi' })).toBeInTheDocument()
      expect(screen.queryByRole('option', { name: 'Company sites' })).not.toBeInTheDocument()
    } finally {
      await i18n.changeLanguage('en')
    }
  })

  it('renders the expected template (id + type) on selection, with zero preview/external calls', async () => {
    render(<MigrationsPage />, { wrapper: wrapper() })

    await waitFor(() => expect(fetchMigrationSourcesMock).toHaveBeenCalled())

    await selectRolesSource()

    await waitFor(() => expect(fetchMigrationColumnsMock).toHaveBeenCalledWith('roles'))

    expect(await screen.findByText('Expected template')).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Field' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Type' })).toBeInTheDocument()
    expect(screen.getByText('name')).toBeInTheDocument()
    expect(screen.getByText('string')).toBeInTheDocument()

    // The expected endpoint and its full request URL are shown alongside the schema.
    expect(screen.getByText('https://external-crm.test/roles')).toBeInTheDocument()
    expect(screen.getByText('GET')).toBeInTheDocument()

    // The canonical sample response is rendered as pretty-printed JSON text.
    expect(screen.getByText(/"current_page": 1/)).toBeInTheDocument()

    // Selecting a source and viewing its template is a static contract lookup:
    // no live external call is made.
    expect(fetchMigrationPreviewMock).not.toHaveBeenCalled()
    expect(screen.queryByText('Editor')).not.toBeInTheDocument()

    // No edit/delete/create control ever appears.
    expect(screen.queryByRole('button', { name: /edit/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /delete/i })).not.toBeInTheDocument()
  })

  it('copies the expected endpoint URL to the clipboard', async () => {
    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    await screen.findByText('Expected template')

    fireEvent.click(screen.getByRole('button', { name: 'Copy URL' }))

    await waitFor(() =>
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('https://external-crm.test/roles'),
    )
  })

  it('copies the sample JSON to the clipboard', async () => {
    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    await screen.findByText('Expected template')

    fireEvent.click(screen.getByRole('button', { name: 'Copy JSON' }))

    await waitFor(() =>
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        JSON.stringify(columnsTemplate().sample, null, 2),
      ),
    )
  })

  it('shows a base-URL-missing hint instead of crashing when base_url is empty', async () => {
    fetchMigrationColumnsMock.mockResolvedValue(
      columnsTemplate({
        request: { method: 'GET', base_url: '', path: 'roles', url: '' },
      }),
    )

    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    await screen.findByText('Expected template')

    expect(screen.getByText('roles')).toBeInTheDocument()
    expect(
      screen.getByText('The base URL is not configured for this source; only the path is shown.'),
    ).toBeInTheDocument()
  })

  it('opens the import dialog for the selected source from the template panel', async () => {
    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    await screen.findByText('Expected template')

    fireEvent.click(screen.getByRole('button', { name: 'Import this source' }))

    expect(await screen.findByRole('dialog', { name: /import roles/i })).toBeInTheDocument()
  })

  it('only fetches and renders the external preview after the on-demand action is clicked', async () => {
    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    await screen.findByText('Expected template')

    expect(fetchMigrationPreviewMock).not.toHaveBeenCalled()
    expect(screen.queryByText('Editor')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Show external preview' }))

    await waitFor(() => expect(fetchMigrationPreviewMock).toHaveBeenCalledWith('roles', 1, 20))

    expect(await screen.findByRole('columnheader', { name: 'Name' })).toBeInTheDocument()
    expect(await screen.findByText('Editor')).toBeInTheDocument()
    expect(screen.getByText('Viewer')).toBeInTheDocument()
  })

  it('paginates the on-demand preview, disabling Next when there is no further page (has_more=false)', async () => {
    fetchMigrationPreviewMock.mockResolvedValue(
      previewPage({ pagination: { page: 1, per_page: 20, total: 2, has_more: false } }),
    )

    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    fireEvent.click(await screen.findByRole('button', { name: 'Show external preview' }))

    await screen.findByText('Editor')

    expect(screen.getByRole('button', { name: /previous/i })).toBeDisabled()
    expect(screen.getByRole('button', { name: /next/i })).toBeDisabled()
  })

  it('advances to the next preview page when has_more is true', async () => {
    fetchMigrationPreviewMock.mockResolvedValueOnce(
      previewPage({ pagination: { page: 1, per_page: 20, total: null, has_more: true } }),
    )

    render(<MigrationsPage />, { wrapper: wrapper() })

    await selectRolesSource()
    fireEvent.click(await screen.findByRole('button', { name: 'Show external preview' }))

    await screen.findByText('Editor')
    expect(screen.getByRole('button', { name: /next/i })).toBeEnabled()

    fetchMigrationPreviewMock.mockResolvedValueOnce(
      previewPage({ rows: [{ name: 'Auditor' }], pagination: { page: 2, per_page: 20, total: null, has_more: false } }),
    )
    fireEvent.click(screen.getByRole('button', { name: /next/i }))

    await waitFor(() => expect(fetchMigrationPreviewMock).toHaveBeenCalledWith('roles', 2, 20))
    expect(await screen.findByText('Auditor')).toBeInTheDocument()
  })
})
