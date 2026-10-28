import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement, ReactNode } from 'react'
import { fireEvent, render as renderWithoutClient, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { WorkOrderWorkflowDetailView } from '@/features/work-order-workflows/work-order-workflow-detail'
import type { CriterionFieldOption, WorkOrderWorkflowDetailWithPermissions } from '@/features/work-order-workflows/types'

/**
 * Spec 0322: the detail translates the criterion field label from its key and
 * shows each status' progress percentage. No edit page; Nome/Attivo/Criteri/
 * Stati edit in place, each save a PATCH of that row's fields alone.
 */

const updateWorkOrderWorkflowMock = vi.fn()
const fetchCriterionFieldsMock = vi.fn()

vi.mock('@/features/work-order-workflows/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-order-workflows/api')>()),
  updateWorkOrderWorkflow: (...args: unknown[]) => updateWorkOrderWorkflowMock(...args),
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
  { field: 'business_function_id', label: 'workOrderWorkflows.criterionFields.business_function_id', source: 'native', for_select_resource: 'business-functions', multi_valued: true, inherited: false },
]

function workOrderWorkflow(overrides: Partial<WorkOrderWorkflowDetailWithPermissions> = {}): WorkOrderWorkflowDetailWithPermissions {
  return {
    id: 9,
    name: 'SOA set',
    is_active: true,
    criteria: [
      { id: 1, field: 'business_function_id', value_id: 5, value_label: 'Soa function' },
      { id: 2, field: 'product_category_id', value_id: 42, value_label: 'Acme category' },
    ],
    statuses: [
      { id: 10, name: 'Open', color: null, sort_order: 0, system_key: 'open', group: 'open', description: null, progress_percentage: null },
      { id: 11, name: 'Alpha', color: 'blue', sort_order: 10, system_key: null, group: 'in_progress', description: null, progress_percentage: null },
      { id: 13, name: 'Closed', color: null, sort_order: 30, system_key: 'completed', group: 'completed', description: null, progress_percentage: null },
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
  updateWorkOrderWorkflowMock.mockReset()
  fetchCriterionFieldsMock.mockReset().mockResolvedValue(CRITERION_FIELDS)
})

describe('WorkOrderWorkflowDetailView — read view', () => {
  it('translates each criterion field label and shows its resolved value', () => {
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)
    expect(screen.getByText('Business function')).toBeInTheDocument()
    expect(screen.getByText('Product category')).toBeInTheDocument()
    expect(screen.getByText('Soa function')).toBeInTheDocument()
    expect(screen.getByText('Acme category')).toBeInTheDocument()
  })

  it('shows the progress percentage of the statuses that have one', () => {
    const detail = workOrderWorkflow()
    detail.statuses[1].progress_percentage = 40
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={detail} />)
    expect(screen.getByText('40%')).toBeInTheDocument()
    expect(screen.queryByText('0%')).not.toBeInTheDocument()
  })
})

describe('WorkOrderWorkflowDetailView — in-place editing ', () => {
  it('has no Edit button: every row is closed, with its pencil ', () => {
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('workOrderWorkflows.form.name')).toBeInTheDocument()
    expect(pencilOf('workOrderWorkflows.form.isActive')).toBeInTheDocument()
    expect(pencilOf('workOrderWorkflows.detail.criteriaTitle')).toBeInTheDocument()
    expect(pencilOf('workOrderWorkflows.detail.statusesTitle')).toBeInTheDocument()
  })

  it('Save on the name sends only the name and reports the change ', async () => {
    updateWorkOrderWorkflowMock.mockResolvedValue(workOrderWorkflow({ name: 'Renamed' }))
    const onChanged = vi.fn()
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} onChanged={onChanged} />)

    fireEvent.click(pencilOf('workOrderWorkflows.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    saveRow()

    await waitFor(() => expect(updateWorkOrderWorkflowMock).toHaveBeenCalledWith(9, { name: 'Renamed' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
  })

  it('Save on Active sends only is_active', async () => {
    updateWorkOrderWorkflowMock.mockResolvedValue(workOrderWorkflow({ is_active: false }))
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    fireEvent.click(pencilOf('workOrderWorkflows.form.isActive') as HTMLElement)
    fireEvent.click(screen.getByRole('switch'))
    saveRow()

    await waitFor(() => expect(updateWorkOrderWorkflowMock).toHaveBeenCalledWith(9, { is_active: false }))
  })

  it('Save on the criteria sends only the criteria array', async () => {
    updateWorkOrderWorkflowMock.mockResolvedValue(workOrderWorkflow())
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    fireEvent.click(pencilOf('workOrderWorkflows.detail.criteriaTitle') as HTMLElement)
    fireEvent.click(screen.getAllByRole('button', { name: 'Remove criterion' })[1])
    saveRow()

    await waitFor(() =>
      expect(updateWorkOrderWorkflowMock).toHaveBeenCalledWith(9, { criteria: [{ field: 'business_function_id', value_id: 5 }] }),
    )
  })

  it('Save on the statuses sends only the statuses, existing ones keeping their id', async () => {
    updateWorkOrderWorkflowMock.mockResolvedValue(workOrderWorkflow())
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    fireEvent.click(pencilOf('workOrderWorkflows.detail.statusesTitle') as HTMLElement)
    fireEvent.change(screen.getByDisplayValue('Alpha'), { target: { value: 'Alpha 2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    const names = screen.getAllByRole('textbox', { name: 'Status name' })
    fireEvent.change(names[2], { target: { value: 'Bravo' } })
    saveRow()

    await waitFor(() => expect(updateWorkOrderWorkflowMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateWorkOrderWorkflowMock.mock.calls[0]
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
    const withTwoCustoms = workOrderWorkflow()
    withTwoCustoms.statuses.splice(2, 0, {
      id: 12, name: 'Bravo', color: 'green', sort_order: 20, system_key: null, group: 'in_progress', description: null, progress_percentage: null,
    })
    updateWorkOrderWorkflowMock.mockResolvedValue(withTwoCustoms)
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={withTwoCustoms} />)

    fireEvent.click(pencilOf('workOrderWorkflows.detail.statusesTitle') as HTMLElement)
    const [alphaHandle] = screen.getAllByRole('button', { name: 'Drag to reorder' })
    alphaHandle.focus()
    fireEvent.keyDown(alphaHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowDown' })
    fireEvent.keyDown(document, { code: 'Space' })
    saveRow()

    await waitFor(() => expect(updateWorkOrderWorkflowMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateWorkOrderWorkflowMock.mock.calls[0]
    expect(Object.keys(payload)).toEqual(['statuses'])
    expect(payload.statuses.map((status: { id?: number }) => status.id)).toEqual([10, 12, 11, 13])
  })

  it('Cancel restores the row without calling the API', () => {
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    fireEvent.click(pencilOf('workOrderWorkflows.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateWorkOrderWorkflowMock).not.toHaveBeenCalled()
  })

  it('Cancel on the statuses brings the saved set back', () => {
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    fireEvent.click(pencilOf('workOrderWorkflows.detail.statusesTitle') as HTMLElement)
    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.cancel') }))
    fireEvent.click(pencilOf('workOrderWorkflows.detail.statusesTitle') as HTMLElement)

    expect(screen.getAllByRole('textbox', { name: 'Status name' })).toHaveLength(3)
    expect(updateWorkOrderWorkflowMock).not.toHaveBeenCalled()
  })

  it('maps a server 422 onto the open criteria row', async () => {
    updateWorkOrderWorkflowMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { criteria: ['These criteria are already used.'] } },
      } as never),
    )
    render(<WorkOrderWorkflowDetailView workOrderWorkflow={workOrderWorkflow()} />)

    fireEvent.click(pencilOf('workOrderWorkflows.detail.criteriaTitle') as HTMLElement)
    fireEvent.click(screen.getAllByRole('button', { name: 'Remove criterion' })[1])
    saveRow()

    expect(await screen.findByText('These criteria are already used.')).toBeInTheDocument()
  })

  it('offers no pencil when update is not allowed ', () => {
    render(
      <WorkOrderWorkflowDetailView
        workOrderWorkflow={workOrderWorkflow({
          permissions: {
            resource: { view: true, create: true, update: false, delete: true, export: true, import: true },
            fields: {},
            actions: { view_activity: false },
          },
        })}
      />,
    )

    expect(pencilOf('workOrderWorkflows.form.name')).not.toBeInTheDocument()
    expect(pencilOf('workOrderWorkflows.detail.criteriaTitle')).not.toBeInTheDocument()
    expect(pencilOf('workOrderWorkflows.detail.statusesTitle')).not.toBeInTheDocument()
  })
})
