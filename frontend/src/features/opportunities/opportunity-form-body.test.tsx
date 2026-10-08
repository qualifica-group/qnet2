import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { OpportunityForm } from '@/features/opportunities/opportunity-form'
import type { FieldPermission, ResourceMeta } from '@/features/authorization/types'
import {
  FULL_PERMISSIONS,
  ROW,
  SELECT_IDS,
  formTestWrapper,
  openRow,
  queryPencil,
  resolveForSelectLabels,
} from '@/features/opportunities/opportunity-form-test-helpers'

/**
 * AC-071 (every field of the contract renders), AC-074 (field permissions:
 * hidden vs not editable). Spec 0171: the title is a form input again
 * (superseding spec 0057 D-5), blank in create.
 *
 * Spec 0198: the create form is a replica of the detail, its rows CLOSED — a
 * row's control exists only once its pencil is pressed. The edit form is gone
 * (the detail edits in place, covered by `opportunity-detail.test.tsx`).
 *
 * The `?lead_id=N` deep-link create-from-lead mode (AC-075), the in-form
 * "Lead" select (AC-086/087), the anagrafica pick (referent scoping, inherited
 * roles and team) and the draft-row behavior (Done/Revert/invalid save) are
 * split into their own files for size (engineering.md §6).
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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args) }
})

function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
}

function renderCreateForm() {
  return render(<OpportunityForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
    wrapper: formTestWrapper(),
  })
}

function permissionsWith(fields: Record<string, FieldPermission>): ResourceMeta {
  return { fields: [], permissions: { ...FULL_PERMISSIONS, fields } }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  for (const key of Object.keys(SELECT_IDS)) {
    delete SELECT_IDS[key]
  }
  createOpportunityMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_PERMISSIONS })
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) =>
    resolveForSelectLabels(resource, params),
  )
})

describe('OpportunityFormBody — fields render (AC-071)', () => {
  // REQUIREMENT CHANGED (spec 0198): the controls no longer render up front —
  // every row is closed (value or placeholder + pencil); the control of a row
  // appears only once its pencil is pressed.
  it('renders every field as a closed row, each opening on its own control (spec 0171, spec 0198)', async () => {
    renderCreateForm()

    // The originating Lead is the one open control: not a row, not a form field.
    await waitFor(() => expect(screen.getByTestId('select-Lead')).toBeInTheDocument())
    for (const testId of [
      'select-Registry',
      'select-Contact',
      'select-Sales rep',
      'select-Reporter',
      'select-Source',
      'select-Supervisor',
      'select-Parent category 1',
    ]) {
      expect(screen.queryByTestId(testId)).not.toBeInTheDocument()
    }
    expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add account manager' })).not.toBeInTheDocument()
    // Spec 0082: the status is COMPUTED, never a form field — and since the
    // 2026-08-05 directive it is not repeated in the form body at all.
    expect(screen.queryByTestId('select-Opportunity Status')).not.toBeInTheDocument()
    expect(screen.queryByText('No status')).not.toBeInTheDocument()

    openRow(ROW.title)
    expect(screen.getByRole('textbox', { name: 'Title' })).toHaveValue('')

    openRow(ROW.registry)
    expect(screen.getByTestId('select-Registry')).toBeInTheDocument()
    // The previous row closed on its own: one editor at a time.
    expect(screen.queryByRole('textbox', { name: 'Title' })).not.toBeInTheDocument()

    // The referent has no pencil until an anagrafica is chosen (see the registry suite).
    for (const [row, testId] of [
      [ROW.commercial, 'select-Sales rep'],
      [ROW.reporter, 'select-Reporter'],
      [ROW.source, 'select-Source'],
      [ROW.supervisor, 'select-Supervisor'],
    ] as const) {
      openRow(row)
      expect(screen.getByTestId(testId)).toBeInTheDocument()
    }

    openRow(ROW.managers)
    expect(screen.getByRole('button', { name: 'Add account manager' })).toBeInTheDocument()

    // User directive 2026-07-29: the create form opens on ONE product-line row.
    openRow(ROW.productLines)
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeInTheDocument()
    expect(screen.getByTestId('select-Parent category 1')).toBeInTheDocument()
    expect(screen.queryByTestId('select-Parent category 2')).not.toBeInTheDocument()

    // User directive 2026-08-05: Sede operativa and Regione are hidden — no row at all.
    expect(screen.queryByTestId('select-Operational site')).not.toBeInTheDocument()
    expect(screen.queryByTestId('select-Region')).not.toBeInTheDocument()
    expect(queryPencil('Operational site')).not.toBeInTheDocument()
  })

  /** Directive 2026-07-21: supervisor is never required (it derives from the linked Lead's Operatore, which may be empty). */
  it('never marks supervisor required', async () => {
    renderCreateForm()

    await waitFor(() => expect(queryPencil(ROW.supervisor)).toBeInTheDocument())
    openRow(ROW.supervisor)

    expect(labelFor('Supervisor')).toHaveTextContent('Supervisor')
    expect(labelFor('Supervisor')).not.toHaveTextContent('*')
  })
})

describe('OpportunityFormBody — field permissions (AC-074)', () => {
  it('does not render a field marked hidden (visible: false)', async () => {
    fetchResourceMetaMock.mockResolvedValue(
      permissionsWith({
        supervisor_id: { visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false },
      }),
    )

    renderCreateForm()

    await waitFor(() => expect(queryPencil(ROW.title)).toBeInTheDocument())
    expect(queryPencil(ROW.supervisor)).not.toBeInTheDocument()
    expect(screen.queryByText(ROW.supervisor)).not.toBeInTheDocument()
    // REQUIREMENT CHANGED (spec 0198 D-3): the anagrafica's cascade writes the supervisor, so with it hidden the row does not open.
    expect(queryPencil(ROW.registry)).not.toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (spec 0198): a non-editable field used to render a
  // disabled control; its row now simply has no pencil (nothing to open).
  it('renders a non-editable field as a plain row with no pencil, rather than hiding it', async () => {
    fetchResourceMetaMock.mockResolvedValue(
      permissionsWith({
        source_id: { visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false },
      }),
    )

    renderCreateForm()

    await waitFor(() => expect(queryPencil(ROW.registry)).toBeInTheDocument())
    expect(screen.getByText(ROW.source)).toBeInTheDocument()
    expect(queryPencil(ROW.source)).not.toBeInTheDocument()
  })
})
