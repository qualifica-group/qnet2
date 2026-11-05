import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteWorkflowForm } from '@/features/quote-workflows/quote-workflow-form'
import type { CriterionFieldOption, QuoteWorkflowDetail } from '@/features/quote-workflows/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0354 AC-008: the create form pre-filled from a copied workflow — name
 * "Copy of ...", active, no criteria, the source's statuses without their ids —
 * submitted through the regular create call.
 */

const createQuoteWorkflowMock = vi.fn()

vi.mock('@/features/quote-workflows/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/quote-workflows/api')>()),
  createQuoteWorkflow: (...args: unknown[]) => createQuoteWorkflowMock(...args),
  fetchCriterionFields: () => Promise.resolve(CRITERION_FIELDS),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: FULL_ACCESS_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span>{`${labels.triggerLabel}:${value ?? 'none'}:${disabled ? 'disabled' : 'enabled'}`}</span>
      <button type="button" disabled={disabled} onClick={() => onChange(23)}>
        {`Pick ${labels.triggerLabel}`}
      </button>
    </div>
  ),
}))

const CRITERION_FIELDS: CriterionFieldOption[] = [
  { field: 'source_id', label: 'quoteWorkflows.criterionFields.source_id', source: 'native', for_select_resource: 'sources', multi_valued: false, inherited: true },
]

const SOURCE: QuoteWorkflowDetail = {
  id: 15,
  name: 'Consulenza',
  is_active: false,
  criteria: [
    { id: 1, field: 'product_category_branch_id', value_id: 23, value_label: 'Consulenza', field_label: 'quoteWorkflows.criterionFields.product_category_branch_id', field_source: 'native' },
  ],
  statuses: [
    { id: 101, name: 'Nuova richiesta', description: 'Appena arrivata', color: 'slate', sort_order: 0, system_key: 'open', group: 'open', requires_note: false },
    { id: 102, name: 'In trattativa', description: null, color: 'amber', sort_order: 1, system_key: null, group: 'pending', requires_note: true },
    { id: 103, name: 'Vinta', description: null, color: 'green', sort_order: 2, system_key: 'closed_won', group: 'closed_won', requires_note: false },
    { id: 104, name: 'Persa', description: null, color: 'red', sort_order: 3, system_key: 'closed_lost', group: 'closed_lost', requires_note: true },
  ],
  created_at: '2026-11-05T00:00:00Z',
  updated_at: '2026-11-05T00:00:00Z',
}

function renderDuplicate() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  render(<QuoteWorkflowForm source={SOURCE} onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createQuoteWorkflowMock.mockReset().mockResolvedValue({ id: 30, name: 'Copy of Consulenza' })
})

describe('QuoteWorkflowForm — duplicate (spec 0354 AC-008)', () => {
  it('starts from the source statuses, a "Copy of" name and no criteria', async () => {
    renderDuplicate()

    expect(screen.getByRole('heading', { name: 'Copy of Consulenza' })).toBeInTheDocument()
    expect(screen.getByDisplayValue('Nuova richiesta')).toBeInTheDocument()
    expect(screen.getByDisplayValue('In trattativa')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Persa')).toBeInTheDocument()
    expect(await screen.findByText(/^Value:none:disabled$/)).toBeInTheDocument()
  })

  it('creates a new active workflow with the copied statuses and the criteria picked', async () => {
    renderDuplicate()

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Source' }))
    fireEvent.click(screen.getByRole('button', { name: 'Pick Value' }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createQuoteWorkflowMock.mock.calls[0]
    expect(payload.name).toBe('Copy of Consulenza')
    expect(payload.is_active).toBe(true)
    expect(payload.criteria).toEqual([{ field: 'source_id', value_id: 23 }])
    expect(payload.statuses).toEqual([
      { name: 'Nuova richiesta', description: 'Appena arrivata', color: 'slate', group: 'open', system_key: 'open', requires_note: false },
      { name: 'In trattativa', description: null, color: 'amber', group: 'pending', system_key: null, requires_note: true },
      { name: 'Vinta', description: null, color: 'green', group: 'closed_won', system_key: 'closed_won', requires_note: false },
      { name: 'Persa', description: null, color: 'red', group: 'closed_lost', system_key: 'closed_lost', requires_note: true },
    ])
  })
})
