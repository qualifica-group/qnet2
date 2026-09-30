import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportStepMapping } from '@/features/imports/wizard/import-step-mapping'
import '@/features/imports/wizard/i18n'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0176 D-5/AC-009: picking the run-wide Campaign prefills the run-wide
 * Fonte from the campaign's own `meta.source` (still freely editable
 * afterward), and Fonte is always required — blocking the submit — whether
 * the Campaign is chosen run-wide or read per row from the file. Split out of `import-step-mapping-campaign.test.tsx`
 * (engineering.md §6 size split): that file owns the Campaign source-toggle
 * mechanics (AC-030..034), this one owns the Fonte delta layered on top.
 */

vi.mock('@/features/imports/wizard/mapping-template-controls', async () => {
  const actual = await vi.importActual<typeof import('@/features/imports/wizard/mapping-template-controls')>(
    '@/features/imports/wizard/mapping-template-controls',
  )
  return { ...actual, SavedTemplatesMenu: () => null }
})

const useForSelectMock = vi.fn()
const useForSelectLabelsMock = vi.fn()

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: (args: unknown) => useForSelectLabelsMock(args),
  }
})

const CAMPAIGN_WITH_SOURCE = {
  id: 9,
  label: 'Spring campaign',
  meta: { operational_site: null, source: { id: 77, name: 'Referral' } },
}
const CAMPAIGN_WITHOUT_SOURCE = {
  id: 5,
  label: 'Autumn campaign',
  meta: { operational_site: null, source: null },
}

vi.mock('@/features/imports/wizard/import-config-relation-select', () => ({
  ImportConfigRelationSelect: ({
    resource,
    value,
    onChange,
    onItemChange,
    triggerLabel,
  }: {
    resource: string
    value: number | null
    onChange: (next: number | null) => void
    onItemChange?: (item: unknown) => void
    triggerLabel: string
  }) => (
    <button
      type="button"
      aria-label={triggerLabel}
      onClick={() => {
        if (resource === 'campaigns') {
          const next = value === CAMPAIGN_WITH_SOURCE.id ? CAMPAIGN_WITHOUT_SOURCE : CAMPAIGN_WITH_SOURCE
          onChange(next.id)
          onItemChange?.(next)
          return
        }
        if (resource === 'sources') {
          onChange(3)
          onItemChange?.(null)
          return
        }
        onChange(1)
        onItemChange?.(null)
      }}
    >
      {value ?? 'none'}
    </button>
  ),
}))

function queryState() {
  return {
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  }
}

function campaignAndSourceRun(): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'configuring',
    original_filename: 'leads.csv',
    total_rows: 3,
    valid_rows: 0,
    warning_rows: 0,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: null,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-09-08T00:00:00Z',
    error_count: 0,
    detected_columns: [
      { key: 'Email', name: 'Email', index: 0, duplicate: false },
      { key: 'Codice campagna', name: 'Codice campagna', index: 1, duplicate: false },
    ],
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: {},
    fields: [
      { id: 'email', label: 'Email', required: false, group: 'contact', type: 'text' },
      { id: 'campaign_code', label: 'Campaign code', required: false, group: 'lead', type: 'text' },
    ],
    global_fields: [
      {
        id: 'campaign_id',
        label: 'Campaign',
        required: true,
        for_select_resource: 'campaigns',
        default: null,
        required_unless_mapped: 'campaign_code',
      },
      {
        id: 'source_id',
        label: 'Source',
        required: true,
        for_select_resource: 'sources',
        default: null,
      },
    ],
    dedup_modes: ['create_new'],
    review_fields: [],
  }
}

function renderStep(initialMapping: Record<string, string> = {}, initialConfig: Record<string, number | null> = {}) {
  const onSubmit = vi.fn()
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ImportStepMapping
        run={campaignAndSourceRun()}
        initialMapping={initialMapping}
        initialDedupStrategy="create_new"
        initialConfig={initialConfig}
        onSubmit={onSubmit}
        isSubmitting={false}
        submitError={null}
      />
    </QueryClientProvider>,
  )
  return { onSubmit }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue(queryState())
  useForSelectLabelsMock.mockReset()
  useForSelectLabelsMock.mockReturnValue(new Map())
})

describe('ImportStepMapping — Fonte prefill and always required (spec 0176)', () => {
  it('prefills the run-wide Source from the picked Campaign meta, still editable', async () => {
    renderStep()

    expect(screen.getByRole('button', { name: 'Source' })).toHaveTextContent('none')

    fireEvent.click(screen.getByRole('button', { name: 'Campaign' }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Source' })).toHaveTextContent('77'))

    // The prefill is not a lock: the user can still override it manually.
    fireEvent.click(screen.getByRole('button', { name: 'Source' }))
    expect(screen.getByRole('button', { name: 'Source' })).toHaveTextContent('3')
  })

  it('leaves the Source untouched when the picked Campaign has none', async () => {
    renderStep()

    // First pick the Campaign that HAS a Fonte, then manually override the
    // prefilled Source — mirrors a real edit before a second Campaign pick
    // (under test) could clobber it.
    fireEvent.click(screen.getByRole('button', { name: 'Campaign' }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Source' })).toHaveTextContent('77'))

    fireEvent.click(screen.getByRole('button', { name: 'Source' }))
    expect(screen.getByRole('button', { name: 'Source' })).toHaveTextContent('3')

    fireEvent.click(screen.getByRole('button', { name: 'Campaign' }))
    await waitFor(() =>
      expect(screen.getByRole('button', { name: 'Campaign' })).toHaveTextContent(
        String(CAMPAIGN_WITHOUT_SOURCE.id),
      ),
    )
    expect(screen.getByRole('button', { name: 'Source' })).toHaveTextContent('3')
  })

  it('shows the required marker on Source with a run-level Campaign', () => {
    renderStep()

    const label = screen.getByText('Source').closest('label')
    expect(label).toHaveTextContent('*')
  })

  it('blocks the submit and flags Source when no Fonte is set', async () => {
    const { onSubmit } = renderStep()

    // Neither Campaign nor Source picked: both required global fields surface
    // a "This field is required." alert.
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() =>
      expect(screen.getAllByText('This field is required.').length).toBeGreaterThanOrEqual(2),
    )
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('still requires Source when the Campaign is read per row from the file', async () => {
    const { onSubmit } = renderStep({ 'Codice campagna': 'campaign_code' })

    const label = screen.getByText('Source').closest('label')
    expect(label).toHaveTextContent('*')

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() => expect(screen.getByText('This field is required.')).toBeInTheDocument())
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('submits a per-row Campaign run once the Source is set', async () => {
    const { onSubmit } = renderStep({ 'Codice campagna': 'campaign_code' }, { source_id: 3 })

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
  })
})
