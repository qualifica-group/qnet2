import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { QuoteWorkflowDetailView } from '@/features/quote-workflows/quote-workflow-detail'
import type { CriterionFieldOption, QuoteWorkflowDetailWithPermissions } from '@/features/quote-workflows/types'

/**
 * Spec 0047 AC-036: the detail resolves a criterion's field label from
 * `field_label`/`field_source`, translating only when native. Spec 0242: no
 * edit page; Nome/Attivo/Criteri/Stati edit in place, each save a PATCH of
 * that row's fields alone.
 */

const updateQuoteWorkflowMock = vi.fn()
const fetchCriterionFieldsMock = vi.fn()

vi.mock('@/features/quote-workflows/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/quote-workflows/api')>()),
  updateQuoteWorkflow: (...args: unknown[]) => updateQuoteWorkflowMock(...args),
  fetchCriterionFields: (...args: unknown[]) => fetchCriterionFieldsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Role-based stand-in of the async value picker (see the create form test). */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    labels: { triggerLabel: string }
  }) => (
    <button type="button" onClick={() => onChange(101)}>
      {`Pick ${labels.triggerLabel} (${value ?? 'none'})`}
    </button>
  ),
}))

const CRITERION_FIELDS: CriterionFieldOption[] = [
  { field: 'source_id', label: 'quoteWorkflows.criterionFields.source_id', source: 'native', for_select_resource: 'sources', multi_valued: false, inherited: true },
]

function quoteWorkflow(overrides: Partial<QuoteWorkflowDetailWithPermissions> = {}): QuoteWorkflowDetailWithPermissions {
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
      {
        id: 2,
        field: 'custom.preferred_supplier',
        value_id: 42,
        value_label: 'Acme Corp',
        field_label: 'Preferred supplier',
        field_source: 'custom',
      },
    ],
    statuses: [
      { id: 10, name: 'Open', color: null, sort_order: 0, system_key: 'open', group: 'open', description: null, requires_note: false },
      { id: 11, name: 'Alpha', color: 'blue', sort_order: 10, system_key: null, group: 'pending', description: null, requires_note: false },
      { id: 13, name: 'Closed', color: null, sort_order: 30, system_key: 'closed_won', group: 'closed_won', description: null, requires_note: false },
    ],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

/** One QueryClient per test: the in-place rows save through React Query. */
function render(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return renderWithoutClient(ui, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: i18n.t(key) }) })

const ROW_HEIGHT = 40

/** jsdom's all-zero rects tie every row; stub a rect whose `top` follows DOM order. */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return { width: 280, height: ROW_HEIGHT, top, bottom: top + ROW_HEIGHT, left: 0, right: 280, x: 0, y: top, toJSON: () => ({}) } as DOMRect
  })
}

/** The keyboard sensor attaches its move/drop listeners in a macrotask. */
const flushSensorAttach = () => new Promise((resolve) => setTimeout(resolve, 0))

const saveRow = () => fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateQuoteWorkflowMock.mockReset()
  fetchCriterionFieldsMock.mockReset().mockResolvedValue(CRITERION_FIELDS)
})

describe('QuoteWorkflowDetailView — criterion field labels (AC-036)', () => {
  it('translates a native criterion field label', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)
    expect(screen.getByText(i18n.t('quoteWorkflows.criterionFields.source_id'))).toBeInTheDocument()
  })

  it('renders a custom criterion field label literally, without translating it', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)
    expect(screen.getByText('Preferred supplier')).toBeInTheDocument()
  })

  it('renders the resolved value label for both native and custom criteria', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)
    expect(screen.getByText('Fiera')).toBeInTheDocument()
    expect(screen.getByText('Acme Corp')).toBeInTheDocument()
  })
})

describe('QuoteWorkflowDetailView — in-place editing (spec 0242)', () => {
  it('has no Edit button: every row is closed, with its pencil (AC-001)', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('quoteWorkflows.form.name')).toBeInTheDocument()
    expect(pencilOf('quoteWorkflows.form.isActive')).toBeInTheDocument()
    expect(pencilOf('quoteWorkflows.detail.criteriaTitle')).toBeInTheDocument()
    expect(pencilOf('quoteWorkflows.detail.statusesTitle')).toBeInTheDocument()
  })

  it('Save on the name sends only the name and reports the change (AC-002)', async () => {
    updateQuoteWorkflowMock.mockResolvedValue(quoteWorkflow({ name: 'Renamed' }))
    const onChanged = vi.fn()
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} onChanged={onChanged} />)

    fireEvent.click(pencilOf('quoteWorkflows.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    saveRow()

    await waitFor(() => expect(updateQuoteWorkflowMock).toHaveBeenCalledWith(9, { name: 'Renamed' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
  })

  it('Save on Active sends only is_active', async () => {
    updateQuoteWorkflowMock.mockResolvedValue(quoteWorkflow({ is_active: false }))
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    fireEvent.click(pencilOf('quoteWorkflows.form.isActive') as HTMLElement)
    fireEvent.click(screen.getByRole('switch'))
    saveRow()

    await waitFor(() => expect(updateQuoteWorkflowMock).toHaveBeenCalledWith(9, { is_active: false }))
  })

  it('Save on the criteria sends only the criteria array', async () => {
    updateQuoteWorkflowMock.mockResolvedValue(quoteWorkflow())
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    fireEvent.click(pencilOf('quoteWorkflows.detail.criteriaTitle') as HTMLElement)
    fireEvent.click(screen.getAllByRole('button', { name: 'Remove criterion' })[1])
    saveRow()

    await waitFor(() =>
      expect(updateQuoteWorkflowMock).toHaveBeenCalledWith(9, { criteria: [{ field: 'source_id', value_id: 5 }] }),
    )
  })

  it('Save on the statuses sends only the statuses, existing ones keeping their id', async () => {
    updateQuoteWorkflowMock.mockResolvedValue(quoteWorkflow())
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    fireEvent.click(pencilOf('quoteWorkflows.detail.statusesTitle') as HTMLElement)
    fireEvent.change(screen.getByDisplayValue('Alpha'), { target: { value: 'Alpha 2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    const names = screen.getAllByRole('textbox', { name: 'Status name' })
    fireEvent.change(names[2], { target: { value: 'Bravo' } })
    saveRow()

    await waitFor(() => expect(updateQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateQuoteWorkflowMock.mock.calls[0]
    expect(id).toBe(9)
    expect(Object.keys(payload)).toEqual(['statuses'])
    expect(payload.statuses.map((status: { id?: number; name: string }) => [status.id, status.name])).toEqual([
      [10, 'Open'],
      [11, 'Alpha 2'],
      [undefined, 'Bravo'],
      [13, 'Closed'],
    ])
  })

  it('Save after a drag sends only the statuses in the new order, with their ids', async () => {
    mockRowRects()
    const withTwoCustoms = quoteWorkflow()
    withTwoCustoms.statuses.splice(2, 0, {
      id: 12, name: 'Bravo', color: 'green', sort_order: 20, system_key: null, group: 'pending', description: null, requires_note: false,
    })
    updateQuoteWorkflowMock.mockResolvedValue(withTwoCustoms)
    render(<QuoteWorkflowDetailView quoteWorkflow={withTwoCustoms} />)

    fireEvent.click(pencilOf('quoteWorkflows.detail.statusesTitle') as HTMLElement)
    const [alphaHandle] = screen.getAllByRole('button', { name: 'Drag to reorder' })
    alphaHandle.focus()
    fireEvent.keyDown(alphaHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })
    saveRow()

    await waitFor(() => expect(updateQuoteWorkflowMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateQuoteWorkflowMock.mock.calls[0]
    expect(Object.keys(payload)).toEqual(['statuses'])
    expect(payload.statuses.map((status: { id?: number }) => status.id)).toEqual([10, 12, 11, 13])
  })

  it('Cancel restores the row without calling the API', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    fireEvent.click(pencilOf('quoteWorkflows.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateQuoteWorkflowMock).not.toHaveBeenCalled()
  })

  it('Cancel on the statuses brings the saved set back', () => {
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    fireEvent.click(pencilOf('quoteWorkflows.detail.statusesTitle') as HTMLElement)
    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))
    fireEvent.click(pencilOf('quoteWorkflows.detail.statusesTitle') as HTMLElement)

    expect(screen.getAllByRole('textbox', { name: 'Status name' })).toHaveLength(3)
    expect(updateQuoteWorkflowMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open criteria row', async () => {
    updateQuoteWorkflowMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { criteria: ['These criteria are already used.'] } },
      } as never),
    )
    render(<QuoteWorkflowDetailView quoteWorkflow={quoteWorkflow()} />)

    fireEvent.click(pencilOf('quoteWorkflows.detail.criteriaTitle') as HTMLElement)
    fireEvent.click(screen.getAllByRole('button', { name: 'Remove criterion' })[1])
    saveRow()

    expect(await screen.findByText('These criteria are already used.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed (AC-003)', () => {
    render(
      <QuoteWorkflowDetailView
        quoteWorkflow={quoteWorkflow({
          permissions: {
            resource: { view: true, create: true, update: false, delete: true, export: true, import: true },
            fields: {},
            actions: { view_activity: false },
          },
        })}
      />,
    )

    expect(pencilOf('quoteWorkflows.form.name')).not.toBeInTheDocument()
    expect(pencilOf('quoteWorkflows.detail.criteriaTitle')).not.toBeInTheDocument()
    expect(pencilOf('quoteWorkflows.detail.statusesTitle')).not.toBeInTheDocument()
  })
})
