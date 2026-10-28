import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { WorkOrderWorkflowForm } from '@/features/work-order-workflows/work-order-workflow-form'
import type { CriterionFieldOption, WorkOrderWorkflowDetail } from '@/features/work-order-workflows/types'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0322 (create = replica of the detail): the name/active rows start
 * closed, the criteria and statuses editors start OPEN; criteria editor
 * add/remove with a value select dependent on the field; the pinned
 * open/completed/closed rows are seeded, editable, and land in the create
 * payload. Mocks the HTTP boundary and `sonner`.
 */

const createWorkOrderWorkflowMock = vi.fn()
const fetchCriterionFieldsMock = vi.fn()

vi.mock('@/features/work-order-workflows/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-order-workflows/api')>()),
  createWorkOrderWorkflow: (...args: unknown[]) => createWorkOrderWorkflowMock(...args),
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
  { field: 'business_function_id', label: 'workOrderWorkflows.criterionFields.business_function_id', source: 'native', for_select_resource: 'business-functions', multi_valued: true, inherited: false },
  { field: 'product_category_id', label: 'workOrderWorkflows.criterionFields.product_category_id', source: 'native', for_select_resource: 'product-categories', multi_valued: true, inherited: false },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}


const CREATED = { id: 9, name: 'SOA set' } as WorkOrderWorkflowDetail

/** Opens a closed row through its pencil. */
function openRow(label: string) {
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label }) }))
}

/** Picks the Business function criterion and a value on the first criteria row. */
async function fillCriterion() {
  fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
  fireEvent.click(await screen.findByRole('option', { name: 'Business function' }))
  fireEvent.click(screen.getByRole('button', { name: 'Pick Value' }))
}

function nameTheWorkflow() {
  openRow('Name')
  fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'SOA set' } })
  fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.apply') }))
}

const save = () => fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

function renderForm(onSuccess = vi.fn()) {
  render(<WorkOrderWorkflowForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper: wrapper() })
  return onSuccess
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createWorkOrderWorkflowMock.mockReset()
  fetchCriterionFieldsMock.mockReset().mockResolvedValue(CRITERION_FIELDS)
})

describe('WorkOrderWorkflowForm — replica of the detail ', () => {
  it('renders the three sections with the name row closed and the editors open', () => {
    renderForm()

    expect(screen.getByRole('heading', { name: 'Details' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Criteria' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Processing statuses' })).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Field' })).toBeInTheDocument()
    expect(screen.getByDisplayValue('Open')).toBeInTheDocument()
  })
})

describe('WorkOrderWorkflowForm — criteria editor ', () => {
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
    fireEvent.click(await screen.findByRole('option', { name: 'Business function' }))

    await waitFor(() => expect(screen.getByText(/^Value:business-functions:.*:enabled$/)).toBeInTheDocument())
  })

  it('offers the translated work order criterion fields', async () => {
    renderForm()

    fireEvent.click(screen.getByRole('combobox', { name: 'Field' }))
    expect(await screen.findByRole('option', { name: 'Business function' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Product category' })).toBeInTheDocument()
  })
})

describe('WorkOrderWorkflowForm — create submit ', () => {
  it('does not call the API when the name is missing, and shows the error on the closed row', async () => {
    renderForm()

    await fillCriterion()
    save()

    expect(await screen.findByText('Name is required.')).toBeInTheDocument()
    expect(createWorkOrderWorkflowMock).not.toHaveBeenCalled()
  })

  it('blocks the save while a status has no name', async () => {
    renderForm()

    nameTheWorkflow()
    await fillCriterion()
    fireEvent.change(screen.getByDisplayValue('Open'), { target: { value: '' } })
    save()

    expect(await screen.findByText('Every status needs a name.')).toBeInTheDocument()
    expect(createWorkOrderWorkflowMock).not.toHaveBeenCalled()
  })

  it('seeds editable open/completed/closed rows and sends them with the added custom row in the create payload', async () => {
    createWorkOrderWorkflowMock.mockResolvedValue(CREATED)
    const onSuccess = renderForm()

    expect(screen.getByDisplayValue('Open')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Completed')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Closed')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add status' }))
    // 4 name inputs now: open (0), the new custom (1, inserted before the pinned
    // tail), completed (2), closed (3).
    const nameInputs = screen.getAllByRole('textbox', { name: 'Status name' })
    fireEvent.change(nameInputs[1], { target: { value: 'In review' } })

    await fillCriterion()
    nameTheWorkflow()
    save()

    await waitFor(() => expect(createWorkOrderWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createWorkOrderWorkflowMock.mock.calls[0]
    expect(payload.statuses).toEqual([
      { name: 'Open', color: null, group: 'open', system_key: 'open', description: null, progress_percentage: null },
      { name: 'In review', color: null, group: 'in_progress', system_key: null, description: null, progress_percentage: null },
      { name: 'Completed', color: null, group: 'completed', system_key: 'completed', description: null, progress_percentage: null },
      { name: 'Closed', color: null, group: 'closed', system_key: 'closed', description: null, progress_percentage: null },
    ])
    expect(payload.criteria).toEqual([{ field: 'business_function_id', value_id: 101 }])
    expect(payload.name).toBe('SOA set')
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(CREATED))
  })

  it('lets the user rename a pinned row up front; the new label lands in the create payload', async () => {
    createWorkOrderWorkflowMock.mockResolvedValue(CREATED)
    renderForm()

    fireEvent.change(screen.getByDisplayValue('Open'), { target: { value: 'Aperto (in corso)' } })
    await fillCriterion()
    nameTheWorkflow()
    save()

    await waitFor(() => expect(createWorkOrderWorkflowMock).toHaveBeenCalledTimes(1))
    const [payload] = createWorkOrderWorkflowMock.mock.calls[0]
    const openStatus = payload.statuses.find((status: { system_key: string | null }) => status.system_key === 'open')
    expect(openStatus.name).toBe('Aperto (in corso)')
  })
})
