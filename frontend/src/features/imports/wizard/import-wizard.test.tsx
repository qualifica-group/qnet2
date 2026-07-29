import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ImportWizard } from '@/features/imports/wizard/import-wizard'
import type { ImportRunDetail, ImportRunSummary } from '@/features/imports/wizard/types'

/**
 * Spec 0033: end-to-end orchestration of the wizard's server-driven state
 * machine (AC-020/021/022/025) — upload through mapping submit, resume via
 * `?runId=`, and the top-level loading/error states.
 */

const analyzeImportMock = vi.fn()
const getImportWizardRunMock = vi.fn()
const configureImportRunMock = vi.fn()
const confirmImportRunMock = vi.fn()
const listMappingTemplatesMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  analyzeImport: (...args: unknown[]) => analyzeImportMock(...args),
  getImportWizardRun: (...args: unknown[]) => getImportWizardRunMock(...args),
  configureImportRun: (...args: unknown[]) => configureImportRunMock(...args),
  confirmImportRun: (...args: unknown[]) => confirmImportRunMock(...args),
  createMappingTemplate: vi.fn(),
  listMappingTemplates: (...args: unknown[]) => listMappingTemplatesMock(...args),
}))

// The mapping step's `SavedTemplatesMenu` (spec 0035) reads the actor from
// auth context; irrelevant to this end-to-end orchestration test, so a
// minimal stub avoids requiring a full `AuthProvider` tree here.
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Test user' } }),
}))

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual =
    await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
      '@/features/for-select/use-for-select',
    )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

function createdRun(overrides: Partial<ImportRunSummary> = {}): ImportRunSummary {
  return {
    id: 1,
    resource: 'leads',
    status: 'analyzing',
    original_filename: 'leads.csv',
    total_rows: 0,
    valid_rows: 0,
    warning_rows: 0,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    ...overrides,
  }
}

function detailRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    ...createdRun(),
    status: 'configuring',
    total_rows: 5,
    error_count: 0,
    detected_columns: [{ key: 'Email', name: 'Email', index: 0, duplicate: false }],
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: { Email: 'email' },
    fields: [{ id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' }],
    global_fields: [
      { id: 'campaign_id', label: 'Campaign', required: true, for_select_resource: 'campaigns', default: null },
    ],
    dedup_modes: ['create_new'],
    ...overrides,
  }
}

function renderWizard(initialEntry: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <MemoryRouter initialEntries={[initialEntry]}>
          <Routes>
            <Route path="/leads/import" element={<ImportWizard domain="leads" />} />
          </Routes>
        </MemoryRouter>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  analyzeImportMock.mockReset()
  getImportWizardRunMock.mockReset()
  configureImportRunMock.mockReset()
  confirmImportRunMock.mockReset()
  listMappingTemplatesMock.mockReset()
  listMappingTemplatesMock.mockResolvedValue([])
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue({
    data: { pages: [{ items: [{ id: 9, label: 'Spring campaign' }] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
})

describe('ImportWizard', () => {
  it('starts on the upload step with the full stepper visible', () => {
    renderWizard('/leads/import')

    expect(screen.getByRole('navigation', { name: 'Progress' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Analyze file' })).toBeInTheDocument()
  })

  it('runs upload -> analysis summary -> mapping+config submit end to end', async () => {
    analyzeImportMock.mockResolvedValue(createdRun())
    getImportWizardRunMock.mockResolvedValue(detailRun())
    configureImportRunMock.mockResolvedValue(createdRun({ status: 'staging' }))

    renderWizard('/leads/import')

    const file = new File(['a'], 'leads.csv', { type: 'text/csv' })
    fireEvent.change(screen.getByLabelText(/File \(\.csv, \.xlsx\)/), { target: { files: [file] } })
    fireEvent.click(screen.getByRole('button', { name: 'Analyze file' }))

    expect(await screen.findByText('File analysis')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Continue to configuration' }))

    // The mapping step now hosts the global config too: set the campaign there.
    fireEvent.click(await screen.findByRole('combobox', { name: 'Campaign' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Spring campaign' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(configureImportRunMock).toHaveBeenCalledWith('leads', 1, {
        column_mapping: { Email: 'email' },
        global_config: { campaign_id: 9 },
        dedup_strategy: 'create_new',
      }),
    )
  })

  it('leaves the mapping step on the configure response, before the refetch lands', async () => {
    getImportWizardRunMock
      .mockResolvedValueOnce(detailRun())
      // The refetch triggered by the transition never resolves: the wizard
      // must advance on the mutation's own response, otherwise the mapping
      // form stays mounted with an enabled submit and a second click 422s
      // against the already-staging run.
      .mockImplementation(() => new Promise(() => {}))
    configureImportRunMock.mockResolvedValue(createdRun({ status: 'staging' }))

    renderWizard('/leads/import?runId=1')

    fireEvent.click(await screen.findByRole('combobox', { name: 'Campaign' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Spring campaign' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    expect(await screen.findByText('Applying mapping…')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save mapping and continue' })).not.toBeInTheDocument()
    expect(configureImportRunMock).toHaveBeenCalledTimes(1)
  })

  it('re-reads the run when a refused configure signals a stale client state', async () => {
    getImportWizardRunMock
      .mockResolvedValueOnce(detailRun())
      .mockResolvedValue(detailRun({ status: 'reviewing' }))
    configureImportRunMock.mockRejectedValue(
      Object.assign(new Error('stale'), { isAxiosError: true, response: { status: 422 } }),
    )

    renderWizard('/leads/import?runId=1')

    fireEvent.click(await screen.findByRole('combobox', { name: 'Campaign' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Spring campaign' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    // The resync lands the wizard on the step the server is actually on
    // instead of leaving the operator stuck on the mapping form.
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'Save mapping and continue' })).not.toBeInTheDocument(),
    )
    expect(getImportWizardRunMock).toHaveBeenCalledTimes(2)
  })

  it('resumes directly at the configuration step from ?runId=', async () => {
    getImportWizardRunMock.mockResolvedValue(detailRun())

    renderWizard('/leads/import?runId=7')

    expect(await screen.findByRole('combobox', { name: 'Campaign' })).toBeInTheDocument()
    expect(getImportWizardRunMock).toHaveBeenCalledWith('leads', 7)
  })

  it('shows a retry action when loading the resumed run fails', async () => {
    getImportWizardRunMock.mockRejectedValueOnce(new Error('network')).mockResolvedValue(detailRun())

    renderWizard('/leads/import?runId=7')

    fireEvent.click(await screen.findByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Campaign' })).toBeInTheDocument())
  })
})
