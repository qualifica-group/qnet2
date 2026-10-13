import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteWorkflowForm } from '@/features/quote-workflows/quote-workflow-form'
import type { CriterionFieldOption, QuoteWorkflowDetail } from '@/features/quote-workflows/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0242 (create = replica of the detail): the name/active rows start
 * closed, the criteria and statuses editors start OPEN. Spec 0047 AC-024
 * (criteria editor add/remove, value select dependent on the field) and
 * AC-004/AC-025 (the pinned open/closed_won/closed_lost rows are seeded and
 * editable, and land in the create payload). Mocks the HTTP boundary and
 * `sonner`.
 */

const createQuoteWorkflowMock = vi.fn()
const fetchCriterionFieldsMock = vi.fn()

vi.mock('@/features/quote-workflows/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/quote-workflows/api')>()),
  createQuoteWorkflow: (...args: unknown[]) => createQuoteWorkflowMock(...args),
  fetchCriterionFields: (...args: unknown[]) => fetchCriterionFieldsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Every field resolves as visible+editable (the `MetaField` fallback, since `fields` is empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/authorization/use-resource-meta', () => ({
  useResourceMeta: () => ({ data: { permissions: FULL_ACCESS_PERMISSIONS }, isError: false, refetch: vi.fn() }),
}))

/**
 * Stub of the async value picker: role-based (a button per selectable
 * option) rather than `data-testid`, per this repo's react-testing rule
 * (a11y-role-first). `disabled` mirrors the real component's prop so the
 * "value depends on field" assertion holds.
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    resource,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    resource: string
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span>{`${labels.triggerLabel}:${resource}:${value ?? 'none'}:${disabled ? 'disabled' : 'enabled'}`}</span>
      <button type="button" disabled={disabled} onClick={() => onChange(101)}>
        {`Pick ${labels.triggerLabel}`}
      </button>
    </div>
  ),
}))

const CRITERION_FIELDS: CriterionFieldOption[] = [
  { field: 'source_id', label: 'quoteWorkflows.criterionFields.source_id', source: 'native', for_select_resource: 'sources', multi_valued: false, inherited: true },
  { field: 'business_function_id', label: 'quoteWorkflows.criterionFields.business_function_id', source: 'native', for_select_resource: 'business-functions', multi_valued: true, inherited: false },
]

/** A custom relational field option (AC-027/D9): `label` is already literal display text, never an i18n key. */
const CUSTOM_CRITERION_FIELD: CriterionFieldOption = {
  field: 'custom.preferred_supplier',
  label: 'Preferred supplier',
  source: 'custom',
  for_select_resource: 'suppliers',
  multi_valued: false,
  inherited: true,
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}


const CREATED = { id: 9, name: 'EMEA workflow' } as QuoteWorkflowDetail

/** Opens a closed row through its pencil. */
function openRow(label: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
}

/** Picks the Source criterion and a value on the first criteria row. */
async function fillCriterion() {
  fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
  fireEvent.click(await screen.findByRole('option', { name: 'Source' }))
  fireEvent.click(screen.getByRole('button', { name: 'Pick Value' }))
}

function nameTheWorkflow() {
  openRow('Name')
  fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'EMEA workflow' } })
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function renderForm(onSuccess = vi.fn()) {
  render(<QuoteWorkflowForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })
  return onSuccess
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createQuoteWorkflowMock.mockReset()
  fetchCriterionFieldsMock.mockReset().mockResolvedValue(CRITERION_FIELDS)
})

describe('QuoteWorkflowForm — replica of the detail (AC-004)', () => {
  it('renders the three sections with the name row closed and the editors open', () => {
    renderForm()

    expect(screen.getByRole('heading', { name: 'Details' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Criteria' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Workflow statuses' })).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Field' })).toBeInTheDocument()
    expect(screen.getByDisplayValue('Open')).toBeInTheDocument()
  })
})

describe('QuoteWorkflowForm — criteria editor (AC-024)', () => {
  it('starts with one empty row and adds/removes rows', async () => {
    renderForm()

    await screen.findByRole('button', { name: 'Add criterion' })
    expect(screen.getAllByRole('combobox', { name: 'Field' })).toHaveLength(1)

    fireEvent.click(screen.getByRole('button', { name: 'Add criterion' }))
    expect(screen.getAllByRole('combobox', { name: 'Field' })).toHaveLength(2)

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove criterion' })[0])
    expect(screen.getAllByRole('combobox', { name: 'Field' })).toHaveLength(1)
  })

  it("disables the value select until a field is picked, then scopes it to the field's resource", async () => {
    renderForm()

    await screen.findByText(/^Value:.*:disabled$/)

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Source' }))

    await waitFor(() => expect(screen.getByText(/^Value:sources:.*:enabled$/)).toBeInTheDocument())
  })

  it('renders a custom field option with its literal label (no t()), a native one translated, and scopes the value select to it (AC-035)', async () => {
    fetchCriterionFieldsMock.mockResolvedValue([...CRITERION_FIELDS, CUSTOM_CRITERION_FIELD])
    renderForm()

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    expect(await screen.findByRole('option', { name: 'Source' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Preferred supplier' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('option', { name: 'Preferred supplier' }))

    await waitFor(() => expect(screen.getByText(/^Value:suppliers:.*:enabled$/)).toBeInTheDocument())
  })
})

describe('QuoteWorkflowForm — create submit (AC-004, AC-025)', () => {
  it('does not call the API when the name is missing, and shows the error on the closed row', async () => {
    renderForm()

    await fillCriterion()
    save()

    expect(await screen.findByText('Name is required.')).toBeInTheDocument()
    expect(createQuoteWorkflowMock).not.toHaveBeenCalled()
  })

  it('blocks the save while a status has no name', async () => {
    renderForm()

    nameTheWorkflow()
    await fillCriterion()
    fireEvent.change(screen.getByDisplayValue('Open'), { target: { value: '' } })
    save()

    expect(await screen.findByText('Every status needs a name.')).toBeInTheDocument()
    expect(createQuoteWorkflowMock).not.toHaveBeenCalled()
  })

  // Requirement changed (user directive 2026-08-07): there is no 'validated'
  // system row at all — a new set starts with the 3 mandatory rows.
  it('seeds editable open/closed_won/closed_lost rows and sends them with the added custom row in the create payload', async () => {
    createQuoteWorkflowMock.mockResolvedValue(CREATED)
    const onSuccess = renderForm()

    expect(screen.getByDisplayValue('Open')).toBeInTheDocument()
    expect(screen.queryByDisplayValue('Validated')).not.toBeInTheDocument()
    expect(screen.getByDisplayValue('Closed (won)')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Closed (lost)')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    // 4 name inputs now: open (0), the new custom (1, inserted before the pinned
    // tail), closed_won (2), closed_lost (3).
    const nameInputs = screen.getAllByRole('textbox', { name: 'Status name' })
    fireEvent.change(nameInputs[1], { target: { value: 'In review' } })

    await fillCriterion()
    nameTheWorkflow()
    save()

    await waitFor(() => expect(createQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createQuoteWorkflowMock.mock.calls[0]
    expect(payload.statuses).toEqual([
      { name: 'Open', color: null, group: 'open', system_key: 'open', description: null, requires_note: false },
      { name: 'In review', color: null, group: 'pending', system_key: null, description: null, requires_note: false },
      { name: 'Closed (won)', color: null, group: 'closed_won', system_key: 'closed_won', description: null, requires_note: false },
      { name: 'Closed (lost)', color: null, group: 'closed_lost', system_key: 'closed_lost', description: null, requires_note: false },
    ])
    expect(payload.criteria).toEqual([{ field: 'source_id', value_id: 101 }])
    expect(payload.name).toBe('EMEA workflow')
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED))
  })

  it('lets the user rename a pinned row up front; the new label lands in the create payload', async () => {
    createQuoteWorkflowMock.mockResolvedValue(CREATED)
    renderForm()

    fireEvent.change(screen.getByDisplayValue('Open'), { target: { value: 'Aperto (in corso)' } })
    await fillCriterion()
    nameTheWorkflow()
    save()

    await waitFor(() => expect(createQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createQuoteWorkflowMock.mock.calls[0]
    const openStatus = payload.statuses.find((status: { system_key: string | null }) => status.system_key === 'open')
    expect(openStatus.name).toBe('Aperto (in corso)')
  })
})
