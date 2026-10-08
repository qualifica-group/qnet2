import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  applyRow,
  clickSave,
  formTestWrapper,
  openRow,
  queryPencil,
  resolveForSelectLabels,
  revertRow,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * Spec 0198 AC-004: the create form is a replica of the detail with its rows
 * CLOSED. A row's control appears only after its pencil; "Done" keeps the value
 * in the draft, "Revert" puts the draft back as the row found it (cascades
 * included), a press outside the row is "Done", and "Save" validates the whole
 * draft — no POST while it is invalid, the message shown under the closed row.
 */

const createOpportunityMock = vi.fn()

vi.mock('@/features/product-lines/product-category-tree-select', async () =>
  await import('@/features/product-lines/product-category-tree-select-stub'))

vi.mock('@/features/product-lines/product-category-root-select', async () => ({
  ProductCategoryRootSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .ProductCategoryRootSelectDouble,
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/opportunities/opportunity-form-test-helpers'))
    .AsyncPaginatedSelectDouble,
}))

vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return { ...actual, createOpportunity: (...args: unknown[]) => createOpportunityMock(...args) }
})

// Closed rows link their related records (`RecordLink`): the open-mode
// preference and the abilities are stubbed rather than dragging an AuthProvider
// in; every ability is granted, so the links render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const TEST_REGISTRY_ID = 10
const TEST_SOURCE_ID = 20

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

async function renderCreateForm() {
  render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: formTestWrapper(),
  })
  await waitFor(() => expect(queryPencil(ROW.title)).toBeInTheDocument())
}

function titleInput(): HTMLElement {
  return screen.getByRole('textbox', { name: 'Title' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  for (const key of Object.keys(SELECT_IDS)) {
    delete SELECT_IDS[key]
  }
  SELECT_IDS.Registry = [TEST_REGISTRY_ID]
  SELECT_IDS.Source = [TEST_SOURCE_ID]
  createOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) => {
    if (resource === 'registries' && params?.ids?.includes(TEST_REGISTRY_ID)) {
      return {
        ...resolveForSelectLabels(resource, params),
        items: [
          {
            id: TEST_REGISTRY_ID,
            label: 'Acme S.p.A.',
            meta: { commercial: null, reporter: null, supervisor: { id: 61, name: 'Ivo Bianchi' }, managers: [] },
          },
        ],
      }
    }
    return resolveForSelectLabels(resource, params)
  })
})

describe('OpportunityFormBody — closed draft rows (spec 0198, AC-004)', () => {
  it('starts with every row closed: no control is rendered until the row pencil is pressed', async () => {
    await renderCreateForm()

    expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument()
    expect(screen.queryByTestId('select-Source')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Done' })).not.toBeInTheDocument()

    openRow(ROW.source)
    expect(screen.getByTestId('select-Source')).toBeInTheDocument()
    // One open row at a time: opening another closes this one, keeping the draft.
    openRow(ROW.title)
    expect(titleInput()).toBeInTheDocument()
    expect(screen.queryByTestId('select-Source')).not.toBeInTheDocument()
  })

  it('"Done" keeps the value in the draft and shows it on the closed row', async () => {
    await renderCreateForm()

    openRow(ROW.source)
    fireEvent.click(screen.getByRole('button', { name: `select Source ${TEST_SOURCE_ID}` }))
    applyRow()

    expect(screen.queryByTestId('select-Source')).not.toBeInTheDocument()
    expect(await screen.findByText('Web')).toBeInTheDocument()
    // The kept value is what the editor finds when the row opens again.
    openRow(ROW.source)
    expect(screen.getByTestId('value-Source')).toHaveTextContent(String(TEST_SOURCE_ID))
  })

  it('"Revert" restores the draft as it was when the row opened', async () => {
    await renderCreateForm()

    openRow(ROW.title)
    fireEvent.change(titleInput(), { target: { value: 'First' } })
    applyRow()

    openRow(ROW.title)
    fireEvent.change(titleInput(), { target: { value: 'Second' } })
    revertRow()

    expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument()
    openRow(ROW.title)
    expect(titleInput()).toHaveValue('First')
  })

  it('"Revert" brings back what the anagrafica cascade wrote besides itself', async () => {
    await renderCreateForm()

    openRow(ROW.registry)
    fireEvent.click(screen.getByRole('button', { name: `select Registry ${TEST_REGISTRY_ID}` }))
    // The anagrafica hands its supervisor down, shown by name on ITS closed row.
    expect(await screen.findByText('Ivo Bianchi')).toBeInTheDocument()

    revertRow()

    await waitFor(() => expect(screen.queryByText('Ivo Bianchi')).not.toBeInTheDocument())
    expect(screen.queryByRole('link', { name: /Acme S\.p\.A\./ })).not.toBeInTheDocument()
  })

  it('a press outside the open row keeps its value, like "Done"', async () => {
    await renderCreateForm()

    openRow(ROW.title)
    fireEvent.change(titleInput(), { target: { value: 'Typed title' } })
    fireEvent.pointerDown(document.body)

    expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument()
    openRow(ROW.title)
    expect(titleInput()).toHaveValue('Typed title')
  })
})

describe('OpportunityFormBody — saving an invalid draft (spec 0198, AC-004)', () => {
  it('makes no POST and shows the errors under the closed rows', async () => {
    await renderCreateForm()

    clickSave()

    // Registry is required, and the default classification row is still empty.
    expect(await screen.findByText('Registry is required.')).toBeInTheDocument()
    expect(screen.getByText('Each row requires both a parent category and a product category.')).toBeInTheDocument()
    expect(createOpportunityMock).not.toHaveBeenCalled()
    // The rows stayed closed: the messages are under the values, not inside an editor.
    expect(screen.queryByTestId('select-Registry')).not.toBeInTheDocument()
    expect(screen.queryByTestId('select-Parent category 1')).not.toBeInTheDocument()
  })
})
