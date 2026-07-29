import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useImportWizard } from '@/features/imports/wizard/use-import-wizard'
import type { ImportRunDetail, ImportRunSummary } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-025: the wizard's step is derived from the run's server
 * status (never client state alone), so reloading mid-`configuring`/
 * `reviewing` resumes at the right step from `GET run`; config/mapping
 * drafts persist locally until the mapping step's single submit.
 */

const analyzeImportMock = vi.fn()
const getImportWizardRunMock = vi.fn()
const configureImportRunMock = vi.fn()
const confirmImportRunMock = vi.fn()
const createMappingTemplateMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  analyzeImport: (...args: unknown[]) => analyzeImportMock(...args),
  getImportWizardRun: (...args: unknown[]) => getImportWizardRunMock(...args),
  configureImportRun: (...args: unknown[]) => configureImportRunMock(...args),
  confirmImportRun: (...args: unknown[]) => confirmImportRunMock(...args),
  createMappingTemplate: (...args: unknown[]) => createMappingTemplateMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  analyzeImportMock.mockReset()
  getImportWizardRunMock.mockReset()
  configureImportRunMock.mockReset()
  confirmImportRunMock.mockReset()
  createMappingTemplateMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('useImportWizard', () => {
  it('starts at step 0 with no run for a fresh wizard', () => {
    const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: null }), {
      wrapper: wrapper(),
    })

    expect(result.current.currentStep).toBe(0)
    expect(result.current.run).toBeNull()
  })

  it('uploads a file, then requires an explicit continue past the analysis summary', async () => {
    analyzeImportMock.mockResolvedValue(createdRun())
    getImportWizardRunMock.mockResolvedValue(detailRun())
    const onRunCreated = vi.fn()

    const { result } = renderHook(
      () => useImportWizard({ domain: 'leads', initialRunId: null, onRunCreated }),
      { wrapper: wrapper() },
    )

    act(() => result.current.upload(new File(['a'], 'a.csv')))

    await waitFor(() => expect(result.current.run?.status).toBe('configuring'))
    expect(onRunCreated).toHaveBeenCalledWith(1)
    // Analysis is ready, but the user has not clicked "continue" yet.
    expect(result.current.currentStep).toBe(0)

    act(() => result.current.advanceFromUpload())
    expect(result.current.currentStep).toBe(1)
  })

  it('resumes directly at the mapping+config step for an in-progress run, skipping the upload gate', async () => {
    getImportWizardRunMock.mockResolvedValue(detailRun())

    const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: 1 }), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current.run?.status).toBe('configuring'))
    expect(result.current.currentStep).toBe(1)
  })

  it('submits the mapping with its bundled global config, calling configure once', async () => {
    getImportWizardRunMock
      .mockResolvedValueOnce(detailRun())
      .mockResolvedValue(detailRun({ status: 'staging' }))
    configureImportRunMock.mockResolvedValue(createdRun({ status: 'staging' }))

    const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: 1 }), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current.run).not.toBeNull())

    act(() => result.current.submitMapping({ Email: 'email' }, 'create_new', { campaign_id: 9 }))

    await waitFor(() =>
      expect(configureImportRunMock).toHaveBeenCalledWith('leads', 1, {
        column_mapping: { Email: 'email' },
        global_config: { campaign_id: 9 },
        dedup_strategy: 'create_new',
      }),
    )
    await waitFor(() => expect(result.current.currentStep).toBe(2))
  })

  it('gives up polling a phase that never advances, and resumes on retry', async () => {
    // A queued job nobody consumes (no queue worker) leaves the run in
    // `analyzing` forever: the poll must stop instead of hammering the API.
    vi.useFakeTimers()
    try {
      getImportWizardRunMock.mockResolvedValue(detailRun({ status: 'analyzing' }))

      const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: 1 }), {
        wrapper: wrapper(),
      })

      await act(async () => {
        await vi.advanceTimersByTimeAsync(0)
      })
      expect(result.current.run?.status).toBe('analyzing')

      // Polling is live while the phase is young.
      await act(async () => {
        await vi.advanceTimersByTimeAsync(5_000)
      })
      expect(getImportWizardRunMock.mock.calls.length).toBeGreaterThan(1)

      // Past the stall window (120s) the poll gives up.
      await act(async () => {
        await vi.advanceTimersByTimeAsync(130_000)
      })
      expect(result.current.isPollingStalled).toBe(true)

      const callsAtStall = getImportWizardRunMock.mock.calls.length
      await act(async () => {
        await vi.advanceTimersByTimeAsync(30_000)
      })
      // At most the single evaluation still in flight when the flag flipped.
      expect(getImportWizardRunMock.mock.calls.length).toBeLessThanOrEqual(callsAtStall + 1)

      await act(async () => {
        result.current.retryPolling()
        await vi.advanceTimersByTimeAsync(0)
      })
      expect(result.current.isPollingStalled).toBe(false)
      expect(getImportWizardRunMock.mock.calls.length).toBeGreaterThan(callsAtStall)
    } finally {
      vi.useRealTimers()
    }
  })

  it('routes processing/completed/failed statuses to the summary step', async () => {
    getImportWizardRunMock.mockResolvedValue(detailRun({ status: 'processing' }))

    const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: 1 }), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current.currentStep).toBe(3))
  })

  it('saves the mapping as a template after a successful configure, without blocking the advance (AC-011)', async () => {
    getImportWizardRunMock
      .mockResolvedValueOnce(detailRun())
      .mockResolvedValue(detailRun({ status: 'staging' }))
    configureImportRunMock.mockResolvedValue(createdRun({ status: 'staging' }))
    createMappingTemplateMock.mockResolvedValue({ id: 9, name: 'Monthly leads' })

    const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: 1 }), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current.run).not.toBeNull())

    act(() =>
      result.current.submitMapping({ Email: 'email' }, 'create_new', { campaign_id: 9 }, { name: 'Monthly leads' }),
    )

    // The wizard advances on the configure response alone.
    await waitFor(() => expect(result.current.currentStep).toBe(2))

    await waitFor(() =>
      expect(createMappingTemplateMock).toHaveBeenCalledWith('leads', {
        name: 'Monthly leads',
        import_run_id: 1,
      }),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalled())
  })

  it('does not block the wizard advance when saving the mapping template fails (AC-011)', async () => {
    getImportWizardRunMock
      .mockResolvedValueOnce(detailRun())
      .mockResolvedValue(detailRun({ status: 'staging' }))
    configureImportRunMock.mockResolvedValue(createdRun({ status: 'staging' }))
    createMappingTemplateMock.mockRejectedValue(new Error('duplicate name'))

    const { result } = renderHook(() => useImportWizard({ domain: 'leads', initialRunId: 1 }), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current.run).not.toBeNull())

    act(() =>
      result.current.submitMapping({ Email: 'email' }, 'create_new', { campaign_id: 9 }, { name: 'Monthly leads' }),
    )

    await waitFor(() => expect(result.current.currentStep).toBe(2))
    await waitFor(() => expect(toast.error).toHaveBeenCalled())
  })
})
