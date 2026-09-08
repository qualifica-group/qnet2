import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportStepMapping } from '@/features/imports/wizard/import-step-mapping'
import '@/features/imports/wizard/i18n'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0108 AC-030..AC-034: the mapping step lets the operator choose where the
 * campaign comes from — one value for the whole run, or a file column read per
 * row — keeps that choice in sync with the column mapping itself, and never
 * submits a run-wide value the backend would reject.
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

vi.mock('@/features/imports/wizard/import-config-relation-select', () => ({
  ImportConfigRelationSelect: ({
    value,
    onChange,
    triggerLabel,
  }: {
    value: number | null
    onChange: (next: number | null) => void
    triggerLabel: string
  }) => (
    <button type="button" aria-label={triggerLabel} onClick={() => onChange(9)}>
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

function campaignRun(): ImportRunDetail {
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
        id: 'product_ids',
        label: 'Products',
        required: false,
        for_select_resource: 'products',
        default: null,
        multiple: true,
        depends_on: 'campaign_id',
      },
    ],
    dedup_modes: ['create_new'],
    review_fields: [],
  }
}

function renderStep(initialMapping: Record<string, string> = {}) {
  const onSubmit = vi.fn()
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ImportStepMapping
        run={campaignRun()}
        initialMapping={initialMapping}
        initialDedupStrategy="create_new"
        initialConfig={{}}
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

describe('ImportStepMapping — campaign source (spec 0108)', () => {
  it('AC-030: offers the two sources and hides the run-wide control once "from file" is chosen', () => {
    renderStep()

    expect(screen.getByRole('button', { name: 'Campaign' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('radio', { name: /from the file/i }))

    expect(screen.queryByRole('button', { name: 'Campaign' })).not.toBeInTheDocument()
  })

  it('AC-031: mapping a column onto the campaign code selects the "from file" source by itself', () => {
    renderStep({ 'Codice campagna': 'campaign_code' })

    expect(screen.getByRole('radio', { name: /from the file/i })).toBeChecked()
    expect(screen.queryByRole('button', { name: 'Campaign' })).not.toBeInTheDocument()
  })

  it('AC-034: the dependent products field is disabled, explaining that the campaign varies per row', () => {
    renderStep({ 'Codice campagna': 'campaign_code' })

    expect(screen.getByRole('button', { name: 'Products' })).toBeDisabled()
    expect(screen.getByText(/read from the file, so it changes row by row/i)).toBeInTheDocument()
  })

  it('AC-032: submitting in "from file" mode sends neither the campaign nor what depends on it', async () => {
    const { onSubmit } = renderStep({ 'Codice campagna': 'campaign_code' })

    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    const [mapping, dedupStrategy, globalConfig] = onSubmit.mock.calls[0]
    expect(mapping['Codice campagna']).toBe('campaign_code')
    expect(dedupStrategy).toBe('create_new')
    expect(globalConfig).not.toHaveProperty('campaign_id')
    expect(globalConfig).not.toHaveProperty('product_ids')
  })

  it('AC-032: submitting in run-wide mode still sends the chosen campaign', async () => {
    const { onSubmit } = renderStep()

    fireEvent.click(screen.getByRole('button', { name: 'Campaign' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
    expect(onSubmit.mock.calls[0][2]).toMatchObject({ campaign_id: 9 })
  })

  it('AC-033: choosing "from file" without mapping a column blocks the submit and says why', async () => {
    const { onSubmit } = renderStep()

    fireEvent.click(screen.getByRole('radio', { name: /from the file/i }))
    fireEvent.click(screen.getByRole('button', { name: 'Save mapping and continue' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(/map a column to campaign code/i)
    expect(onSubmit).not.toHaveBeenCalled()
  })
})
