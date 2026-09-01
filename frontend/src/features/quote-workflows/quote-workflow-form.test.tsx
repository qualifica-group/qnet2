import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuoteWorkflowForm } from '@/features/quote-workflows/quote-workflow-form'
import type {
  CriterionFieldOption,
  QuoteWorkflowDetailWithPermissions,
} from '@/features/quote-workflows/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0047 AC-024 (3 sections; criteria editor add/remove, value select
 * dependent on the field) and AC-025 (`<SortableList>` statuses editor: the
 * pinned open/closed_won/closed_lost rows have no drag handle/remove action; custom rows are
 * reorderable and the order lands in the payload). Mirrors
 * `opportunity-status-form.test.tsx`/`status-reorder-sheet.test.tsx`'s
 * mocking boundaries (HTTP + `sonner`), driving the SAME real
 * `<SortableList>`/keyboard sensor.
 */

const createQuoteWorkflowMock = vi.fn()
const updateQuoteWorkflowMock = vi.fn()
const fetchCriterionFieldsMock = vi.fn()

vi.mock('@/features/quote-workflows/api', () => ({
  createQuoteWorkflow: (...args: unknown[]) => createQuoteWorkflowMock(...args),
  updateQuoteWorkflow: (...args: unknown[]) => updateQuoteWorkflowMock(...args),
  fetchCriterionFields: (...args: unknown[]) => fetchCriterionFieldsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Every field resolves as visible+editable (the `MetaField` fallback, since `fields` is empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/quote-workflows/use-quote-workflow-form-meta', () => ({
  useQuoteWorkflowFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
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

const ROW_HEIGHT = 40

/** jsdom's all-zero rects tie every row; stub a rect whose `top` follows DOM order (mirrors `status-reorder-sheet.test.tsx`). */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return {
      width: 280,
      height: ROW_HEIGHT,
      top,
      bottom: top + ROW_HEIGHT,
      left: 0,
      right: 280,
      x: 0,
      y: top,
      toJSON: () => ({}),
    } as DOMRect
  })
}

/** The keyboard sensor attaches its move/drop listeners in a macrotask. */
async function flushSensorAttach() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function quoteWorkflow(
  overrides: Partial<QuoteWorkflowDetailWithPermissions> = {},
): QuoteWorkflowDetailWithPermissions {
  return {
    id: 9,
    name: 'EMEA workflow',
    is_active: true,
    criteria: [
      {
        id: 1,
        field: 'source_id',
        value_id: 5,
        value_label: 'Fiera',
        field_label: 'quoteWorkflows.criterionFields.source_id',
        field_source: 'native',
      },
    ],
    statuses: [
      { id: 10, name: 'Open', color: null, sort_order: 0, system_key: 'open', group: 'open', description: null, requires_note: false },
      { id: 11, name: 'Alpha', color: 'blue', sort_order: 10, system_key: null, group: 'pending', description: null, requires_note: false },
      { id: 12, name: 'Bravo', color: 'green', sort_order: 20, system_key: null, group: 'pending', description: null, requires_note: false },
      { id: 13, name: 'Closed', color: null, sort_order: 30, system_key: 'closed_won', group: 'closed_won', description: null, requires_note: false },
    ],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  mockRowRects()
  createQuoteWorkflowMock.mockReset()
  updateQuoteWorkflowMock.mockReset()
  fetchCriterionFieldsMock.mockReset().mockResolvedValue(CRITERION_FIELDS)
})

describe('QuoteWorkflowForm — 3 sections (AC-024)', () => {
  it('renders the general, criteria and statuses sections', async () => {
    render(
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('heading', { name: 'Details' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Criteria' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Workflow statuses' })).toBeInTheDocument()
  })
})

describe('QuoteWorkflowForm — criteria editor (AC-024)', () => {
  it('starts with one empty row and adds/removes rows', async () => {
    render(
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await screen.findByRole('button', { name: 'Add criterion' })
    expect(screen.getAllByRole('combobox', { name: 'Field' })).toHaveLength(1)

    fireEvent.click(screen.getByRole('button', { name: 'Add criterion' }))
    expect(screen.getAllByRole('combobox', { name: 'Field' })).toHaveLength(2)

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove criterion' })[0])
    expect(screen.getAllByRole('combobox', { name: 'Field' })).toHaveLength(1)
  })

  it('disables the value select until a field is picked, then scopes it to the field\'s resource', async () => {
    render(
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await screen.findByText(/^Value:.*:disabled$/)

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Source' }))

    await waitFor(() => expect(screen.getByText(/^Value:sources:.*:enabled$/)).toBeInTheDocument())
  })

  it('renders a custom field option with its literal label (no t()), a native one translated, and scopes the value select to it (AC-035)', async () => {
    fetchCriterionFieldsMock.mockResolvedValue([...CRITERION_FIELDS, CUSTOM_CRITERION_FIELD])

    render(
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    expect(await screen.findByRole('option', { name: 'Source' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Preferred supplier' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('option', { name: 'Preferred supplier' }))

    await waitFor(() => expect(screen.getByText(/^Value:suppliers:.*:enabled$/)).toBeInTheDocument())
  })
})

describe('QuoteWorkflowForm — statuses editor (AC-025)', () => {
  it('pins open first and closed last, without a handle or a remove action', async () => {
    render(
      <QuoteWorkflowForm
        mode={{ type: 'edit', quoteWorkflow: quoteWorkflow() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByDisplayValue('Alpha')
    // 2 custom rows -> 2 drag handles; the 2 pinned rows never get one.
    expect(screen.getAllByRole('button', { name: 'Drag to reorder' })).toHaveLength(2)
    // Only the custom rows can be removed.
    expect(screen.getAllByRole('button', { name: 'Remove status' })).toHaveLength(2)

    const rows = screen.getAllByRole('listitem')
    expect(rows[0]).toHaveTextContent('Open')
    expect(rows[rows.length - 1]).toHaveTextContent('Closed')
  })

  it('sends the reordered custom statuses in the update payload after a drag', async () => {
    updateQuoteWorkflowMock.mockResolvedValue(quoteWorkflow())
    const onSuccess = vi.fn()

    render(
      <QuoteWorkflowForm
        mode={{ type: 'edit', quoteWorkflow: quoteWorkflow() }}
        onSuccess={onSuccess}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    await screen.findByDisplayValue('Alpha')

    const [alphaHandle] = screen.getAllByRole('button', { name: 'Drag to reorder' })
    alphaHandle.focus()
    fireEvent.keyDown(alphaHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateQuoteWorkflowMock.mock.calls[0]
    expect(payload.statuses.map((status: { id?: number }) => status.id)).toEqual([10, 12, 11, 13])
  })

  // Requirement changed (user directive 2026-08-07): there is no 'validated'
  // system row at all — a new set starts with the 3 mandatory rows.
  it('seeds editable open/closed_won/closed_lost rows and sends them with the added custom row in the create payload', async () => {
    createQuoteWorkflowMock.mockResolvedValue(quoteWorkflow())
    const onSuccess = vi.fn()

    render(
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The 3 mandatory pinned rows are present and editable from the start
    // (seeded); nothing seeds a 'Validated' row.
    expect(screen.getByDisplayValue('Open')).toBeInTheDocument()
    expect(screen.queryByDisplayValue('Validated')).not.toBeInTheDocument()
    expect(screen.getByDisplayValue('Closed (won)')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Closed (lost)')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    // 4 name inputs now: open (0), the new custom (1, inserted before the pinned
    // tail), closed_won (2), closed_lost (3).
    const nameInputs = screen.getAllByRole('textbox', { name: 'Status name' })
    fireEvent.change(nameInputs[1], { target: { value: 'In review' } })

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Source' }))
    fireEvent.click(screen.getByRole('button', { name: 'Pick Value' }))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'EMEA workflow' } })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createQuoteWorkflowMock.mock.calls[0]
    expect(payload.statuses).toEqual([
      { name: 'Open', color: null, group: 'open', system_key: 'open', description: null, requires_note: false },
      { name: 'In review', color: null, group: 'pending', system_key: null, description: null, requires_note: false },
      { name: 'Closed (won)', color: null, group: 'closed_won', system_key: 'closed_won', description: null, requires_note: false },
      { name: 'Closed (lost)', color: null, group: 'closed_lost', system_key: 'closed_lost', description: null, requires_note: false },
    ])
    expect(payload.criteria).toEqual([{ field: 'source_id', value_id: 101 }])
    expect(onSuccess).toHaveBeenCalled()
  })

  it('lets the user rename a pinned row up front; the new label lands in the create payload', async () => {
    createQuoteWorkflowMock.mockResolvedValue(quoteWorkflow())

    render(
      <QuoteWorkflowForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByDisplayValue('Open'), { target: { value: 'Aperto (in corso)' } })

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Source' }))
    fireEvent.click(screen.getByRole('button', { name: 'Pick Value' }))
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'EMEA workflow' } })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createQuoteWorkflowMock.mock.calls[0]
    const openRow = payload.statuses.find((status: { system_key: string | null }) => status.system_key === 'open')
    expect(openRow.name).toBe('Aperto (in corso)')
  })
})
