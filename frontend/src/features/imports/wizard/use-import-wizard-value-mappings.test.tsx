import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useImportWizard } from '@/features/imports/wizard/use-import-wizard'
import type { ImportRunDetail, ValueMappings } from '@/features/imports/wizard/types'

/**
 * Spec 0348 AC-016: `value_mappings` travel with `configure`; the template
 * body stays `{name, import_run_id}` (the server snapshots them from the run).
 */

const getImportWizardRunMock = vi.fn()
const configureImportRunMock = vi.fn()
const createMappingTemplateMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  analyzeImport: vi.fn(),
  getImportWizardRun: (...args: unknown[]) => getImportWizardRunMock(...args),
  configureImportRun: (...args: unknown[]) => configureImportRunMock(...args),
  confirmImportRun: vi.fn(),
  createMappingTemplate: (...args: unknown[]) => createMappingTemplateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const RUN_SUMMARY = {
  id: 1,
  resource: 'request-management',
  status: 'configuring' as const,
  original_filename: 'requests.xlsx',
  total_rows: 0,
  valid_rows: 0,
  warning_rows: 0,
  error_rows: 0,
  duplicate_rows: 0,
  imported_rows: null,
  modified_rows: 0,
  has_error_report: false,
  created_at: '2026-11-03T00:00:00Z',
}

const DETAIL_RUN: ImportRunDetail = {
  ...RUN_SUMMARY,
  error_count: 0,
  detected_columns: [{ key: 'Stato', name: 'Stato', index: 0, duplicate: false }],
  column_mapping: null,
  global_config: null,
  dedup_strategy: null,
  suggested_mapping: null,
  fields: [],
  global_fields: [],
  dedup_modes: ['create_new'],
}

const VALUE_MAPPINGS: ValueMappings = {
  workflow_status: {
    NR: { id: 7 },
    'Nuovo Contatto': { create: { name: 'Nuovo Contatto', group: 'open' } },
  },
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  getImportWizardRunMock.mockReset()
  configureImportRunMock.mockReset()
  createMappingTemplateMock.mockReset()
  getImportWizardRunMock.mockResolvedValue(DETAIL_RUN)
  configureImportRunMock.mockResolvedValue({ ...RUN_SUMMARY, status: 'staging' })
  createMappingTemplateMock.mockResolvedValue({ id: 2, name: 'DIL' })
})

describe('useImportWizard value mappings', () => {
  it('sends value_mappings with configure and saves the template without them', async () => {
    const { result } = renderHook(
      () => useImportWizard({ domain: 'request-management', initialRunId: 1 }),
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(result.current.run).not.toBeNull())

    act(() =>
      result.current.submitMapping({ Stato: 'workflow_status' }, 'create_new', { product_category_id: 26 }, { name: 'DIL' }, VALUE_MAPPINGS),
    )

    await waitFor(() =>
      expect(configureImportRunMock).toHaveBeenCalledWith('request-management', 1, {
        column_mapping: { Stato: 'workflow_status' },
        global_config: { product_category_id: 26 },
        dedup_strategy: 'create_new',
        value_mappings: VALUE_MAPPINGS,
      }),
    )
    // The server snapshots the saved value mappings from the run itself.
    await waitFor(() =>
      expect(createMappingTemplateMock).toHaveBeenCalledWith('request-management', { name: 'DIL', import_run_id: 1 }),
    )
  })

  it('pre-fills a global field from its backend default', async () => {
    getImportWizardRunMock.mockResolvedValue({
      ...DETAIL_RUN,
      global_fields: [
        {
          id: 'product_category_id',
          label: 'x',
          required: true,
          for_select_resource: 'product-categories',
          default: 26,
        },
      ],
    })
    const { result } = renderHook(
      () => useImportWizard({ domain: 'request-management', initialRunId: 1 }),
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(result.current.run).not.toBeNull())

    expect(result.current.configValues).toEqual({ product_category_id: 26 })
  })
})
